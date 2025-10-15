<?php

declare(strict_types=1);

namespace Phlag\Evaluations\Cache;

use Phlag\Redis\RedisClient;

/**
 * @phpstan-type EvaluationCachePayload array{
 *     variant: string|null,
 *     reason: string,
 *     rollout: int,
 *     payload?: array<string, mixed>,
 *     bucket?: int
 * }
 */
final class FlagCacheRepository
{
    private const DEFAULT_SNAPSHOT_TTL_SECONDS = 300;

    private const DEFAULT_EVALUATION_TTL_SECONDS = 300;

    private const INVALIDATION_CHANNEL = 'phlag.flags.invalidated';

    private ?RedisClient $redis = null;

    private bool $arrayFallback = false;

    private int $snapshotTtlSeconds;

    private int $evaluationTtlSeconds;

    /**
     * @var array<string, array{payload: array<string, mixed>, expires_at: int}>
     */
    private array $arraySnapshots = [];

    /**
     * @var array<string, array{payload: EvaluationCachePayload, expires_at: int}>
     */
    private array $arrayEvaluations = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $arrayEvaluationIndex = [];

    /**
     * @var array<string, int>
     */
    private array $arrayEvaluationIndexExpiry = [];

    public function __construct(?RedisClient $redis = null, ?int $snapshotTtlSeconds = null, ?int $evaluationTtlSeconds = null)
    {
        $this->redis = $redis;
        $this->snapshotTtlSeconds = $this->normalizeTtl($snapshotTtlSeconds, self::DEFAULT_SNAPSHOT_TTL_SECONDS);
        $this->evaluationTtlSeconds = $this->normalizeTtl($evaluationTtlSeconds, self::DEFAULT_EVALUATION_TTL_SECONDS);

        if ($redis === null) {
            $this->enableFallback();
        }
    }

    /**
     * Retrieve a cached flag snapshot payload.
     *
     * @return array<string, mixed>|null
     */
    public function getSnapshot(string $projectKey, string $environmentKey): ?array
    {
        $redis = $this->redis;

        if ($this->arrayFallback || $redis === null) {
            $this->enableFallback();

            return $this->getSnapshotFromArray($projectKey, $environmentKey);
        }

        try {
            $payload = $redis->get($this->snapshotKey($projectKey, $environmentKey));
        } catch (\Throwable) {
            $this->enableFallback();

            return $this->getSnapshotFromArray($projectKey, $environmentKey);
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            /** @var EvaluationCachePayload|null $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->forgetSnapshot($projectKey, $environmentKey);

            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function storeSnapshot(string $projectKey, string $environmentKey, array $snapshot): void
    {
        $redis = $this->redis;

        if ($this->arrayFallback || $redis === null) {
            $this->enableFallback();
            $this->storeSnapshotInArray($projectKey, $environmentKey, $snapshot);

            return;
        }

        try {
            $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        try {
            $redis->setex(
                $this->snapshotKey($projectKey, $environmentKey),
                $this->snapshotTtlSeconds,
                $encoded
            );
        } catch (\Throwable) {
            $this->enableFallback();
            $this->storeSnapshotInArray($projectKey, $environmentKey, $snapshot);
        }
    }

    public function forgetSnapshot(string $projectKey, string $environmentKey): void
    {
        $redis = $this->redis;

        if ($this->arrayFallback || $redis === null) {
            $this->enableFallback();
            $this->forgetSnapshotFromArray($projectKey, $environmentKey);

            return;
        }

        try {
            $redis->del([$this->snapshotKey($projectKey, $environmentKey)]);
        } catch (\Throwable) {
            $this->enableFallback();
            $this->forgetSnapshotFromArray($projectKey, $environmentKey);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @return EvaluationCachePayload|null
     */
    public function getEvaluation(
        string $projectKey,
        string $environmentKey,
        string $flagKey,
        ?string $userIdentifier,
        array $attributes,
        ?string $flagSignature = null
    ): ?array {
        $redis = $this->redis;

        if ($this->arrayFallback || $redis === null) {
            $this->enableFallback();

            return $this->getEvaluationFromArray($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $flagSignature);
        }

        try {
            $payload = $redis->get(
                $this->evaluationKey($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $flagSignature)
            );
        } catch (\Throwable) {
            $this->enableFallback();

            return $this->getEvaluationFromArray($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $flagSignature);
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        if (! array_key_exists('variant', $decoded)) {
            return null;
        }

        if ($decoded['variant'] !== null && ! is_string($decoded['variant'])) {
            return null;
        }

        if (! is_string($decoded['reason'] ?? null)) {
            return null;
        }

        if (! is_int($decoded['rollout'] ?? null)) {
            return null;
        }

        if (array_key_exists('payload', $decoded) && ! is_array($decoded['payload'])) {
            return null;
        }

        if (array_key_exists('bucket', $decoded) && ! is_int($decoded['bucket'])) {
            return null;
        }

        /** @var EvaluationCachePayload $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @param  EvaluationCachePayload  $payload
     */
    public function storeEvaluation(
        string $projectKey,
        string $environmentKey,
        string $flagKey,
        ?string $userIdentifier,
        array $attributes,
        array $payload,
        ?string $flagSignature = null
    ): void {
        $redis = $this->redis;

        if ($this->arrayFallback || $redis === null) {
            $this->enableFallback();
            $this->storeEvaluationInArray($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $payload, $flagSignature);

            return;
        }

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $evaluationKey = $this->evaluationKey($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $flagSignature);

        try {
            $redis->setex(
                $evaluationKey,
                $this->evaluationTtlSeconds,
                $encoded
            );

            $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

            $redis->sadd($indexKey, [$evaluationKey]);
            $redis->expire($indexKey, $this->evaluationTtlSeconds);
        } catch (\Throwable) {
            $this->enableFallback();
            $this->storeEvaluationInArray($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $payload, $flagSignature);
        }
    }

    public function forgetEvaluations(string $projectKey, string $environmentKey): void
    {
        $redis = $this->redis;

        if ($this->arrayFallback || $redis === null) {
            $this->enableFallback();
            $this->forgetEvaluationsFromArray($projectKey, $environmentKey);

            return;
        }

        $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

        try {
            $members = $redis->smembers($indexKey);

            if ($members !== []) {
                $redis->del($members);
            }

            $redis->del([$indexKey]);
        } catch (\Throwable) {
            $this->enableFallback();
            $this->forgetEvaluationsFromArray($projectKey, $environmentKey);
        }
    }

    public function publishInvalidation(string $projectKey, string $environmentKey): void
    {
        $redis = $this->redis;

        if ($this->arrayFallback || $redis === null) {
            return;
        }

        $message = [
            'project' => $projectKey,
            'environment' => $environmentKey,
        ];

        try {
            $payload = json_encode($message, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        try {
            $redis->publish(self::INVALIDATION_CHANNEL, $payload);
        } catch (\Throwable) {
            $this->enableFallback();
        }
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     */
    private function evaluationKey(
        string $projectKey,
        string $environmentKey,
        string $flagKey,
        ?string $userIdentifier,
        array $attributes,
        ?string $flagSignature = null
    ): string {
        return sprintf(
            'flag:evaluation:%s:%s:%s:%s:%s',
            $this->encodeSegment($projectKey),
            $this->encodeSegment($environmentKey),
            $this->encodeSegment($flagKey),
            $this->encodeSegment($flagSignature ?? '0'),
            $this->hashContext($userIdentifier, $attributes)
        );
    }

    private function evaluationIndexKey(string $projectKey, string $environmentKey): string
    {
        return sprintf(
            'flag:evaluation:index:%s:%s',
            $this->encodeSegment($projectKey),
            $this->encodeSegment($environmentKey)
        );
    }

    private function snapshotKey(string $projectKey, string $environmentKey): string
    {
        return sprintf(
            'flag:snapshot:%s:%s',
            $this->encodeSegment($projectKey),
            $this->encodeSegment($environmentKey)
        );
    }

    /**
     * Normalize user identifier and attributes into a stable hash.
     *
     * @param  array<string, array<int, string>>  $attributes
     */
    private function hashContext(?string $userIdentifier, array $attributes): string
    {
        ksort($attributes);

        foreach ($attributes as $key => $values) {
            sort($values);
            $attributes[$key] = $values;
        }

        $payload = [
            'user' => $userIdentifier ?? '',
            'attributes' => $attributes,
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $encoded = '';
        }

        return hash('sha1', $encoded);
    }

    private function encodeSegment(string $value): string
    {
        return str_replace([':', '{', '}', ' ', "\n", "\r", "\t"], '_', strtolower($value));
    }

    private function enableFallback(): void
    {
        $this->arrayFallback = true;
        $this->redis = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSnapshotFromArray(string $projectKey, string $environmentKey): ?array
    {
        $key = $this->snapshotKey($projectKey, $environmentKey);

        if (! isset($this->arraySnapshots[$key])) {
            return null;
        }

        if ($this->arraySnapshots[$key]['expires_at'] < $this->now()) {
            unset($this->arraySnapshots[$key]);

            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = $this->arraySnapshots[$key]['payload'];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function storeSnapshotInArray(string $projectKey, string $environmentKey, array $snapshot): void
    {
        $key = $this->snapshotKey($projectKey, $environmentKey);

        $this->arraySnapshots[$key] = [
            'payload' => $snapshot,
            'expires_at' => $this->expiresAt($this->snapshotTtlSeconds),
        ];
    }

    private function forgetSnapshotFromArray(string $projectKey, string $environmentKey): void
    {
        unset($this->arraySnapshots[$this->snapshotKey($projectKey, $environmentKey)]);
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @return EvaluationCachePayload|null
     */
    private function getEvaluationFromArray(
        string $projectKey,
        string $environmentKey,
        string $flagKey,
        ?string $userIdentifier,
        array $attributes,
        ?string $flagSignature = null
    ): ?array {
        $key = $this->evaluationKey($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $flagSignature);

        if (! isset($this->arrayEvaluations[$key])) {
            $this->cleanupExpiredIndex($projectKey, $environmentKey);

            return null;
        }

        if ($this->arrayEvaluations[$key]['expires_at'] < $this->now()) {
            unset($this->arrayEvaluations[$key]);
            $this->removeEvaluationFromIndex($projectKey, $environmentKey, $key);

            return null;
        }

        /** @var EvaluationCachePayload $payload */
        $payload = $this->arrayEvaluations[$key]['payload'];

        return $payload;
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @param  EvaluationCachePayload  $payload
     */
    private function storeEvaluationInArray(
        string $projectKey,
        string $environmentKey,
        string $flagKey,
        ?string $userIdentifier,
        array $attributes,
        array $payload,
        ?string $flagSignature = null
    ): void {
        $key = $this->evaluationKey($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $flagSignature);
        $expiresAt = $this->expiresAt($this->evaluationTtlSeconds);

        $this->arrayEvaluations[$key] = [
            'payload' => $payload,
            'expires_at' => $expiresAt,
        ];

        $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

        if (! isset($this->arrayEvaluationIndex[$indexKey])) {
            $this->arrayEvaluationIndex[$indexKey] = [];
        }

        if (! in_array($key, $this->arrayEvaluationIndex[$indexKey], true)) {
            $this->arrayEvaluationIndex[$indexKey][] = $key;
        }

        $this->arrayEvaluationIndexExpiry[$indexKey] = $expiresAt;
    }

    private function forgetEvaluationsFromArray(string $projectKey, string $environmentKey): void
    {
        $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

        if (! isset($this->arrayEvaluationIndex[$indexKey])) {
            return;
        }

        foreach ($this->arrayEvaluationIndex[$indexKey] as $evaluationKey) {
            unset($this->arrayEvaluations[$evaluationKey]);
        }

        unset($this->arrayEvaluationIndex[$indexKey], $this->arrayEvaluationIndexExpiry[$indexKey]);
    }

    private function cleanupExpiredIndex(string $projectKey, string $environmentKey): void
    {
        $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

        if (! isset($this->arrayEvaluationIndexExpiry[$indexKey])) {
            return;
        }

        if ($this->arrayEvaluationIndexExpiry[$indexKey] >= $this->now()) {
            return;
        }

        unset($this->arrayEvaluationIndex[$indexKey], $this->arrayEvaluationIndexExpiry[$indexKey]);
    }

    private function removeEvaluationFromIndex(string $projectKey, string $environmentKey, string $evaluationKey): void
    {
        $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

        if (! isset($this->arrayEvaluationIndex[$indexKey])) {
            return;
        }

        $this->arrayEvaluationIndex[$indexKey] = array_values(array_filter(
            $this->arrayEvaluationIndex[$indexKey],
            static fn (string $value): bool => $value !== $evaluationKey
        ));

        if ($this->arrayEvaluationIndex[$indexKey] === []) {
            unset($this->arrayEvaluationIndex[$indexKey], $this->arrayEvaluationIndexExpiry[$indexKey]);
        }
    }

    private function expiresAt(int $ttl): int
    {
        return $this->now() + $ttl;
    }

    private function normalizeTtl(?int $ttl, int $default): int
    {
        if ($ttl !== null && $ttl > 0) {
            return $ttl;
        }

        return $default;
    }

    private function now(): int
    {
        return time();
    }
}
