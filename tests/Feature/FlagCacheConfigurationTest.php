<?php

declare(strict_types=1);

use Phlag\Evaluations\Cache\FlagCacheRepository;

it('resolves configured TTLs from config', function (): void {
    config()->set('flag_cache.snapshot_ttl', 120);
    config()->set('flag_cache.evaluation_ttl', 240);

    /** @var FlagCacheRepository $repository */
    $repository = app(FlagCacheRepository::class);

    $snapshotTtl = (fn () => $this->snapshotTtlSeconds)->call($repository);
    $evaluationTtl = (fn () => $this->evaluationTtlSeconds)->call($repository);

    expect($snapshotTtl)->toBe(120);
    expect($evaluationTtl)->toBe(240);
});

it('falls back to default TTLs when invalid values are provided', function (): void {
    $repository = new FlagCacheRepository(null, 0, -5);

    $snapshotTtl = (fn () => $this->snapshotTtlSeconds)->call($repository);
    $evaluationTtl = (fn () => $this->evaluationTtlSeconds)->call($repository);

    expect($snapshotTtl)->toBe(300);
    expect($evaluationTtl)->toBe(300);
});

it('skips snapshot caching when disabled', function (): void {
    $repository = new FlagCacheRepository(
        redis: null,
        snapshotTtlSeconds: 300,
        evaluationTtlSeconds: 300,
        snapshotsEnabled: false,
        evaluationsEnabled: true
    );

    $repository->storeSnapshot('project', 'production', ['example' => 'value']);

    expect($repository->getSnapshot('project', 'production'))->toBeNull();
});

it('skips evaluation caching when disabled', function (): void {
    $repository = new FlagCacheRepository(
        redis: null,
        snapshotTtlSeconds: 300,
        evaluationTtlSeconds: 300,
        snapshotsEnabled: true,
        evaluationsEnabled: false
    );

    $repository->storeEvaluation(
        'project',
        'production',
        'flag',
        'user-123',
        ['country' => ['US']],
        [
            'variant' => 'enabled',
            'reason' => 'test',
            'rollout' => 100,
        ],
        'signature'
    );

    expect($repository->getEvaluation(
        'project',
        'production',
        'flag',
        'user-123',
        ['country' => ['US']],
        'signature'
    ))->toBeNull();
});
