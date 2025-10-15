<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Phlag\Auth\Jwt\Configuration;
use Phlag\Auth\Jwt\JwtTokenIssuer;
use Phlag\Auth\Jwt\JwtTokenVerifier;
use Tests\Support\FrozenClock;
use Tests\Support\TestKeys;

it('issues and verifies RSA tokens with required claims', function (): void {
    $now = 1_700_000_000;
    $configuration = Configuration::fromArray([
        'keys' => [
            'active' => [
                'id' => TestKeys::ACTIVE_KEY_ID,
                'private_key' => TestKeys::RSA_PRIVATE_KEY,
                'public_key' => TestKeys::RSA_PUBLIC_KEY,
            ],
        ],
        'ttl' => 600,
        'clock_skew' => 0,
    ]);

    $clock = new FrozenClock((new DateTimeImmutable)->setTimestamp($now));

    $issuer = new JwtTokenIssuer($configuration, $clock);
    $verifier = new JwtTokenVerifier($configuration, $clock);

    $token = $issuer->issue([
        'sub' => 'user-123',
        'roles' => ['admin'],
    ]);

    $result = $verifier->verify($token->value());

    expect($result->isValid())->toBeTrue();
    expect($result->claims()->subject())->toBe('user-123');
    expect($result->claims()->get('roles'))->toEqual(['admin']);
    expect($result->claims()->issuedAt())->toBe($now);
    expect($result->claims()->expiresAt())->toBe($now + 600);

    $decodedHeader = json_decode(base64_decode(explode('.', $token->value())[0], true), true, 512, JSON_THROW_ON_ERROR);
    expect($decodedHeader['alg'])->toBe('RS256');
    expect($decodedHeader['kid'])->toBe(TestKeys::ACTIVE_KEY_ID);
});

it('falls back to HMAC signing when RSA keys are absent', function (): void {
    $configuration = Configuration::fromArray([
        'keys' => [
            'secret' => 'dev-secret',
        ],
        'ttl' => 120,
    ]);

    $clock = FrozenClock::fromTimestamp(1_700_000_000);
    $issuer = new JwtTokenIssuer($configuration, $clock);
    $verifier = new JwtTokenVerifier($configuration, $clock);

    $token = $issuer->issue([
        'sub' => 'local-user',
    ]);

    $result = $verifier->verify($token->value());
    expect($result->isValid())->toBeTrue();
    expect($result->claims()->subject())->toBe('local-user');
});

it('rejects tokens missing required claims', function (): void {
    $configuration = Configuration::fromArray([
        'keys' => [
            'secret' => 'dev-secret',
        ],
        'required_claims' => ['sub', 'exp'],
    ]);

    $clock = FrozenClock::fromTimestamp(1_700_000_000);
    $issuer = new JwtTokenIssuer($configuration, $clock);

    $issuer->issue([
        'exp' => 1_700_000_100,
    ]);
})->throws(RuntimeException::class);

it('validates tokens signed with the previous RSA key', function (): void {
    $now = 1_700_000_000;

    $configuration = Configuration::fromArray([
        'keys' => [
            'active' => [
                'id' => TestKeys::ACTIVE_KEY_ID,
                'private_key' => TestKeys::RSA_PRIVATE_KEY,
                'public_key' => TestKeys::RSA_PUBLIC_KEY,
            ],
            'previous' => [
                'id' => TestKeys::PREVIOUS_KEY_ID,
                'public_key' => TestKeys::PREVIOUS_PUBLIC_KEY,
            ],
        ],
        'ttl' => 300,
        'clock_skew' => 0,
    ]);

    $clock = FrozenClock::fromTimestamp($now);
    $verifier = new JwtTokenVerifier($configuration, $clock);

    $claims = [
        'sub' => 'legacy-user',
        'iat' => $now,
        'exp' => $now + 200,
    ];

    $token = JWT::encode(
        $claims,
        TestKeys::PREVIOUS_RSA_PRIVATE_KEY,
        Configuration::RSA_ALGORITHM,
        TestKeys::PREVIOUS_KEY_ID
    );

    $result = $verifier->verify($token);

    expect($result->isValid())->toBeTrue();
    expect($result->claims()->subject())->toBe('legacy-user');
});

it('flags expired tokens as failures', function (): void {
    $configuration = Configuration::fromArray([
        'keys' => [
            'secret' => 'dev-secret',
        ],
        'ttl' => 60,
        'clock_skew' => 0,
    ]);

    $clock = FrozenClock::fromTimestamp(1_700_000_000);
    $issuer = new JwtTokenIssuer($configuration, $clock);
    $verifier = new JwtTokenVerifier($configuration, $clock);

    $token = $issuer->issue([
        'sub' => 'slow-user',
        'exp' => 1_700_000_010,
    ]);

    $clock->advanceSeconds(20);

    $result = $verifier->verify($token->value());

    expect($result->isValid())->toBeFalse();
    expect($result->code())->toBe('token_expired');
    expect($result->message())->toBe('The token has expired.');
});
