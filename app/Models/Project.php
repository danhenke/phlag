<?php

declare(strict_types=1);

namespace Phlag\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property-read \Illuminate\Support\Carbon|null $created_at
 * @property-read \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Support\Collection<int, Environment> $environments
 */
class Project extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'key',
        'name',
        'description',
        'metadata',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Use the project key as the route identifier.
     */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * Project environments.
     *
     * @return HasMany<Environment, static>
     */
    public function environments(): HasMany
    {
        /** @var HasMany<Environment, static> $relation */
        $relation = $this->hasMany(Environment::class)->orderBy('name');

        return $relation;
    }

    /**
     * Project flags.
     *
     * @return HasMany<Flag, static>
     */
    public function flags(): HasMany
    {
        /** @var HasMany<Flag, static> $relation */
        $relation = $this->hasMany(Flag::class);

        return $relation;
    }
}
