<?php

declare(strict_types=1);

namespace Phlag\Models;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $project_id
 * @property string $environment_id
 * @property string|null $name
 * @property string $key_hash
 * @property array<int, string>|null $roles
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApiCredential extends Model
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
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'project_id',
        'environment_id',
        'name',
        'roles',
        'key_hash',
        'is_active',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'bool',
        'roles' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Belongs to a project.
     *
     * @return BelongsTo<Project, static>
     */
    public function project(): BelongsTo
    {
        /** @var BelongsTo<Project, static> $relation */
        $relation = $this->belongsTo(Project::class);

        return $relation;
    }

    /**
     * Belongs to an environment.
     *
     * @return BelongsTo<Environment, static>
     */
    public function environment(): BelongsTo
    {
        /** @var BelongsTo<Environment, static> $relation */
        $relation = $this->belongsTo(Environment::class);

        return $relation;
    }

    /**
     * Determine if the credential is currently usable for issuing tokens.
     */
    public function isUsable(?DateTimeImmutable $now = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at instanceof Carbon) {
            $now ??= new DateTimeImmutable;

            return $this->expires_at->toDateTimeImmutable() > $now;
        }

        return true;
    }
}
