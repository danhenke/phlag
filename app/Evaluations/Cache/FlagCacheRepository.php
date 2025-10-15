<?php

declare(strict_types=1);

namespace Phlag\Evaluations\Cache;

use Phlag\Redis\RedisClient;

final class FlagCacheRepository
{
    private const SNAPSHOT_TTL_SECONDS = 300;

    private const EVALUATION_TTL_SECONDS = 300;

    private const INVALIDATION_CHANNEL = 'phlag.flags.invalidated';

    private ?RedisClient $redis = null;

    private bool $arrayFallback = false;

    /**
     * @var array<string, array{payload: array<string, mixed>, expires_at: int}>
     */
    private array $arraySnapshots = [];

    /**
     * @var array<string, array{payload: array<string, mixed>, expires_at: int}>
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

    public function __construct(?RedisClient $redis = null)
    {
        $this->redis = $redis;

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
        if ($this->arrayFallback || $this->redis === null) {
            $this->enableFallback();

            return $this->getSnapshotFromArray($projectKey, $environmentKey);
        }

        try {
            $payload = $this->redis?->get($this->snapshotKey($projectKey, $environmentKey));
        } catch (\Throwable) {
            $this->enableFallback();

            return $this->getSnapshotFromArray($projectKey, $environmentKey);
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->forgetSnapshot($projectKey, $environmentKey);

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function storeSnapshot(string $projectKey, string $environmentKey, array $snapshot): void
    {
        if ($this->arrayFallback || $this->redis === null) {
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
            $this->redis?->setex(
                $this->snapshotKey($projectKey, $environmentKey),
                self::SNAPSHOT_TTL_SECONDS,
                $encoded
            );
        } catch (\Throwable) {
            $this->enableFallback();
            $this->storeSnapshotInArray($projectKey, $environmentKey, $snapshot);
        }
    }

    public function forgetSnapshot(string $projectKey, string $environmentKey): void
    {
        if ($this->arrayFallback || $this->redis === null) {
            $this->enableFallback();
            $this->forgetSnapshotFromArray($projectKey, $environmentKey);

            return;
        }

        try {
            $this->redis?->del([$this->snapshotKey($projectKey, $environmentKey)]);
        } catch (\Throwable) {
            $this->enableFallback();
            $this->forgetSnapshotFromArray($projectKey, $environmentKey);
        }
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @return array<string, mixed>|null
     */
    public function getEvaluation(
        string $projectKey,
        string $environmentKey,
        string $flagKey,
        ?string $userIdentifier,
        array $attributes,
        ?string $flagSignature = null
    ): ?array {
        if ($this->arrayFallback || $this->redis === null) {
            $this->enableFallback();

            return $this->getEvaluationFromArray($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $flagSignature);
        }

        try {
            $payload = $this->redis?->get(
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

        return $decoded;
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
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
        if ($this->arrayFallback || $this->redis === null) {
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
            $this->redis?->setex(
                $evaluationKey,
                self::EVALUATION_TTL_SECONDS,
                $encoded
            );

            $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

            $this->redis?->sadd($indexKey, [$evaluationKey]);
            $this->redis?->expire($indexKey, self::EVALUATION_TTL_SECONDS);
        } catch (\Throwable) {
            $this->enableFallback();
            $this->storeEvaluationInArray($projectKey, $environmentKey, $flagKey, $userIdentifier, $attributes, $payload, $flagSignature);
        }
    }

    public function forgetEvaluations(string $projectKey, string $environmentKey): void
    {
        if ($this->arrayFallback || $this->redis === null) {
            $this->enableFallback();
            $this->forgetEvaluationsFromArray($projectKey, $environmentKey);

            return;
        }

        $indexKey = $this->evaluationIndexKey($projectKey, $environmentKey);

        try {
            /** @var array<int, string> $members */
            $members = $this->redis?->smembers($indexKey) ?? [];

            if ($members !== []) {
                $this->redis?->del($members);
            }

            $this->redis?->del([$indexKey]);
        } catch (\Throwable) {
            $this->enableFallback();
            $this->forgetEvaluationsFromArray($projectKey, $environmentKey);
        }
    }

    public function publishInvalidation(string $projectKey, string $environmentKey): void
    {
        if ($this->arrayFallback || $this->redis === null) {
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
            $this->redis?->publish(self::INVALIDATION_CHANNEL, $payload);
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

        return $this->arraySnapshots[$key]['payload'];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function storeSnapshotInArray(string $projectKey, string $environmentKey, array $snapshot): void
    {
        $key = $this->snapshotKey($projectKey, $environmentKey);

        $this->arraySnapshots[$key] = [
            'payload' => $snapshot,
            'expires_at' => $this->expiresAt(self::SNAPSHOT_TTL_SECONDS),
        ];
    }

    private function forgetSnapshotFromArray(string $projectKey, string $environmentKey): void
    {
        unset($this->arraySnapshots[$this->snapshotKey($projectKey, $environmentKey)]);
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @return array<string, mixed>|null
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

        return $this->arrayEvaluations[$key]['payload'];
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @param  array<string, mixed>  $payload
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
        $expiresAt = $this->expiresAt(self::EVALUATION_TTL_SECONDS);

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

    private function now(): int
    {
        return time();
    }
}
