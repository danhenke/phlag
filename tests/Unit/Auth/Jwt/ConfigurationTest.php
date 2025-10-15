<?php

declare(strict_types=1);

use Phlag\Auth\Jwt\Configuration;
use Phlag\Auth\Jwt\KeyPair;
use Phlag\Auth\Jwt\PublicKey;
use Phlag\Auth\Jwt\Secret;

it('parses RSA configuration from array', function (): void {
    $config = Configuration::fromArray([
        'keys' => [
            'active' => [
                'id' => 'kid-123',
                'private_key' => 'private',
                'public_key' => 'public',
            ],
            'previous' => [
                'id' => 'kid-122',
                'public_key' => 'old-public',
            ],
        ],
        'ttl' => 7200,
        'clock_skew' => 30,
        'required_claims' => ['sub', 'exp', 'iat', 'aud'],
    ]);

    expect($config->usesRsa())->toBeTrue();
    expect($config->algorithm())->toBe(Configuration::RSA_ALGORITHM);
    expect($config->ttl())->toBe(7200);
    expect($config->clockSkew())->toBe(30);
    expect($config->requiredClaims())->toEqual(['sub', 'exp', 'iat', 'aud']);
    expect($config->activeKeyPair())->toBeInstanceOf(KeyPair::class);
    expect($config->previousKey())->toBeInstanceOf(PublicKey::class);
    expect($config->secret())->toBeNull();
});

it('parses HMAC configuration using fallback secret', function (): void {
    $config = Configuration::fromArray([
        'keys' => [
            'secret' => 'local-secret',
        ],
    ]);

    expect($config->usesRsa())->toBeFalse();
    expect($config->algorithm())->toBe(Configuration::HMAC_ALGORITHM);
    expect($config->activeKeyPair())->toBeNull();
    expect($config->previousKey())->toBeNull();
    expect($config->secret())->toBeInstanceOf(Secret::class);
});

it('throws when RSA configuration is incomplete', function (): void {
    Configuration::fromArray([
        'keys' => [
            'active' => [
                'id' => 'kid-123',
                'public_key' => 'missing-private',
            ],
        ],
    ]);
})->throws(RuntimeException::class);
