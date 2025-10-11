<?php

declare(strict_types=1);

namespace Phlag\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Project environments.
     *
     * @return HasMany<Environment, static>
     */
    public function environments(): HasMany
    {
        /** @var HasMany<Environment, static> $relation */
        $relation = $this->hasMany(Environment::class);

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
