<?php

declare(strict_types=1);

namespace Tests\Support;

use const OPENSSL_KEYTYPE_RSA;

use RuntimeException;

use function openssl_pkey_export;
use function openssl_pkey_get_details;
use function openssl_pkey_new;

final class TestKeys
{
    public const ACTIVE_KEY_ID = 'kid-active';

    public const PREVIOUS_KEY_ID = 'kid-previous';

    /** @var array{private: string, public: string}|null */
    private static ?array $activePair = null;

    /** @var array{private: string, public: string}|null */
    private static ?array $previousPair = null;

    public static function activePrivateKey(): string
    {
        return self::keyPair(self::$activePair)['private'];
    }

    public static function activePublicKey(): string
    {
        return self::keyPair(self::$activePair)['public'];
    }

    public static function previousPrivateKey(): string
    {
        return self::keyPair(self::$previousPair)['private'];
    }

    public static function previousPublicKey(): string
    {
        return self::keyPair(self::$previousPair)['public'];
    }

    /**
     * @param  array{private: string, public: string}|null  $pair
     * @return array{private: string, public: string}
     */
    private static function keyPair(?array &$pair): array
    {
        if ($pair === null) {
            $pair = self::generateKeyPair();
        }

        return $pair;
    }

    /**
     * @return array{private: string, public: string}
     */
    private static function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Unable to generate RSA key pair for tests.');
        }

        if (! openssl_pkey_export($resource, $privateKey)) {
            throw new RuntimeException('Unable to export RSA private key for tests.');
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false || ! isset($details['key'])) {
            throw new RuntimeException('Unable to extract RSA public key for tests.');
        }

        /** @var string $publicKey */
        $publicKey = $details['key'];

        return [
            'private' => $privateKey,
            'public' => $publicKey,
        ];
    }
}
