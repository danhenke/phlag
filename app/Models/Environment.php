<?php

declare(strict_types=1);

namespace Phlag\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $project_id
 * @property string $key
 * @property bool $is_default
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property-read \Illuminate\Support\Carbon|null $created_at
 * @property-read \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Support\Collection<int, ApiCredential> $apiCredentials
 */
class Environment extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'project_id',
        'key',
        'name',
        'description',
        'is_default',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'bool',
        'metadata' => 'array',
    ];

    /**
     * Use the environment key as the route identifier.
     */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return BelongsTo<Project, static>
     */
    public function project(): BelongsTo
    {
        /** @var BelongsTo<Project, static> $relation */
        $relation = $this->belongsTo(Project::class);

        return $relation;
    }

    /**
     * @return HasMany<Evaluation, static>
     */
    public function evaluations(): HasMany
    {
        /** @var HasMany<Evaluation, static> $relation */
        $relation = $this->hasMany(Evaluation::class);

        return $relation;
    }

    /**
     * @return HasMany<ApiCredential, static>
     */
    public function apiCredentials(): HasMany
    {
        /** @var HasMany<ApiCredential, static> $relation */
        $relation = $this->hasMany(ApiCredential::class);

        return $relation;
    }
}
