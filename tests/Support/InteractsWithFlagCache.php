<?php

declare(strict_types=1);

namespace Tests\Support;

use Phlag\Evaluations\Cache\FlagCacheRepository;
use Phlag\Evaluations\Cache\FlagSignatureHasher;
use Phlag\Models\Flag;

trait InteractsWithFlagCache
{
    protected function flagCache(): FlagCacheRepository
    {
        return app(FlagCacheRepository::class);
    }

    protected function flagSignature(Flag $flag): string
    {
        /** @var FlagSignatureHasher $hasher */
        $hasher = app(FlagSignatureHasher::class);

        return $hasher->hash($flag);
    }

    /**
     * @param  array<string, array<int, string>>  $attributes
     * @return array<string, mixed>|null
     */
    protected function cachedEvaluation(
        Flag $flag,
        string $projectKey,
        string $environmentKey,
        ?string $userIdentifier,
        array $attributes
    ): ?array {
        return $this->flagCache()->getEvaluation(
            $projectKey,
            $environmentKey,
            $flag->key,
            $userIdentifier,
            $attributes,
            $this->flagSignature($flag)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function cachedSnapshot(string $projectKey, string $environmentKey): ?array
    {
        return $this->flagCache()->getSnapshot($projectKey, $environmentKey);
    }

    protected function forgetFlagCaches(string $projectKey, string $environmentKey): void
    {
        $cache = $this->flagCache();
        $cache->forgetSnapshot($projectKey, $environmentKey);
        $cache->forgetEvaluations($projectKey, $environmentKey);
    }
}
