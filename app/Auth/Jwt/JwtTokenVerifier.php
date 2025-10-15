<?php

declare(strict_types=1);

namespace Phlag\Auth\Jwt;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Phlag\Support\Clock\Clock;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

use function array_key_exists;
use function trim;

final class JwtTokenVerifier
{
    public function __construct(
        private readonly Configuration $configuration,
        private readonly Clock $clock,
    ) {}

    public function verify(string $token): TokenVerificationResult
    {
        if (trim($token) === '') {
            return TokenVerificationResult::failure('token_missing', 'Authorization token is missing.');
        }

        $timestamp = $this->clock->now()->getTimestamp();
        $previousTimestamp = JWT::$timestamp;
        $previousLeeway = JWT::$leeway;
        JWT::$timestamp = $timestamp;
        JWT::$leeway = $this->configuration->clockSkew();

        try {
            $headers = new stdClass;
            $payload = $this->configuration->usesRsa()
                ? $this->decodeWithRsaKeys($token, $headers)
                : $this->decodeWithSecret($token, $headers);

            $claims = TokenClaims::fromPayload($payload);

            foreach ($this->configuration->requiredClaims() as $claim) {
                if (! $claims->has($claim)) {
                    return TokenVerificationResult::failure(
                        'claim_missing',
                        "The required claim [{$claim}] is missing from the token."
                    );
                }
            }

            return TokenVerificationResult::success($claims);
        } catch (ExpiredException) {
            return TokenVerificationResult::failure('token_expired', 'The token has expired.');
        } catch (BeforeValidException) {
            return TokenVerificationResult::failure(
                'token_not_yet_valid',
                'The token cannot be used yet. Check the not-before (nbf) or issued-at (iat) claims.'
            );
        } catch (SignatureInvalidException) {
            return TokenVerificationResult::failure('token_signature_invalid', 'Token signature verification failed.');
        } catch (UnexpectedValueException $exception) {
            return TokenVerificationResult::failure(
                'token_invalid',
                'The token could not be decoded.',
                ['error' => $exception->getMessage()]
            );
        } finally {
            JWT::$timestamp = $previousTimestamp;
            JWT::$leeway = $previousLeeway;
        }
    }

    private function decodeWithSecret(string $token, stdClass $headers): stdClass
    {
        $secret = $this->configuration->secret();

        if ($secret === null) {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return JWT::decode(
            $token,
            new Key($secret->value(), Configuration::HMAC_ALGORITHM),
            $headers
        );
    }

    private function decodeWithRsaKeys(string $token, stdClass $headers): stdClass
    {
        $active = $this->configuration->activeKeyPair();

        if ($active === null) {
            throw new RuntimeException('Active RSA keys are not configured.');
        }

        $keys = [
            $active->keyId() => new Key($active->publicKey(), Configuration::RSA_ALGORITHM),
        ];

        $previous = $this->configuration->previousKey();

        if ($previous !== null) {
            $keys[$previous->keyId()] = new Key($previous->publicKey(), Configuration::RSA_ALGORITHM);
        }

        $decoded = JWT::decode($token, $keys, $headers);

        if (! array_key_exists('kid', (array) $headers) && $previous !== null) {
            // When the provider did not embed a kid header we cannot differentiate keys,
            // so surface a deterministic failure to avoid accepting an unexpected key.
            throw new UnexpectedValueException('"kid" empty, unable to lookup correct key');
        }

        return $decoded;
    }
}
