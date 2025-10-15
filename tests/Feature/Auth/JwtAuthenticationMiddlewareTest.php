<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Phlag\Auth\Jwt\Configuration;
use Phlag\Auth\Jwt\JwtTokenIssuer;
use Phlag\Auth\Jwt\JwtTokenVerifier;
use Phlag\Auth\Jwt\TokenClaims;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\TestKeys;

beforeEach(function (): void {
    config()->set('jwt', [
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
            'secret' => null,
        ],
        'ttl' => 300,
        'clock_skew' => 0,
    ]);

    app()->forgetInstance(Configuration::class);
    app()->forgetInstance(JwtTokenIssuer::class);
    app()->forgetInstance(JwtTokenVerifier::class);

    if (! Route::has('middleware-protected')) {
        Route::middleware('auth.jwt')->get('/middleware-protected', function () {
            /** @var TokenClaims|null $claims */
            $claims = request()->attributes->get('jwt.claims');

            return response()->json([
                'user' => $claims?->subject(),
            ]);
        })->name('middleware-protected');
    }
});

it('rejects requests without bearer tokens', function (): void {
    $response = $this->getJson('/middleware-protected');

    $response->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('allows requests with valid tokens', function (): void {
    /** @var JwtTokenIssuer $issuer */
    $issuer = app(JwtTokenIssuer::class);

    $token = $issuer->issue([
        'sub' => 'middleware-user',
    ]);

    $response = $this->getJson('/middleware-protected', [
        'Authorization' => 'Bearer '.$token->value(),
    ]);

    $response->assertOk()
        ->assertJsonPath('user', 'middleware-user');
});

it('returns signature failure details when token is tampered', function (): void {
    /** @var JwtTokenIssuer $issuer */
    $issuer = app(JwtTokenIssuer::class);

    $token = $issuer->issue([
        'sub' => 'middleware-user',
    ])->value();

    // Flip the final character to emulate tampering.
    $tampered = rtrim($token, '=').'x';

    $response = $this->getJson('/middleware-protected', [
        'Authorization' => 'Bearer '.$tampered,
    ]);

    $response->assertStatus(Response::HTTP_UNAUTHORIZED)
        ->assertJsonPath('error.code', 'token_signature_invalid');
});
