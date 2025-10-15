<?php

declare(strict_types=1);

namespace Phlag\Evaluations\Cache;

use Phlag\Models\Flag;

final class FlagSignatureHasher
{
    public function hash(Flag $flag): string
    {
        $payload = [
            'updated_at' => $flag->updated_at?->toISOString(),
            'is_enabled' => (bool) $flag->is_enabled,
            'variants' => $flag->variants,
            'rules' => $flag->rules,
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return hash('sha1', serialize($payload));
        }

        return hash('sha1', $encoded);
    }
}
