<?php

declare(strict_types=1);

namespace Phlag\Auth\ApiKeys;

use Phlag\Models\ApiCredential;

use function hash;
use function hash_equals;

final class ApiCredentialHasher
{
    /**
     * Generate a deterministic hash for the provided API key.
     */
    public static function make(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }

    /**
     * Verify if the provided API key matches the stored credential hash.
     */
    public static function verify(ApiCredential $credential, string $apiKey): bool
    {
        return hash_equals($credential->key_hash, self::make($apiKey));
    }
}
