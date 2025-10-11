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
 */
class Flag extends Model
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
        'is_enabled',
        'variants',
        'rules',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_enabled' => 'bool',
        'variants' => 'array',
        'rules' => 'array',
    ];

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
}
