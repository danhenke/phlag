<?php

declare(strict_types=1);

namespace Phlag\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $project_id
 * @property string $environment_id
 * @property string $flag_id
 */
class Evaluation extends Model
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
        'environment_id',
        'flag_id',
        'flag_key',
        'variant',
        'evaluation_reason',
        'user_identifier',
        'request_context',
        'evaluation_payload',
        'evaluated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'request_context' => 'array',
        'evaluation_payload' => 'array',
        'evaluated_at' => 'datetime',
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
     * @return BelongsTo<Environment, static>
     */
    public function environment(): BelongsTo
    {
        /** @var BelongsTo<Environment, static> $relation */
        $relation = $this->belongsTo(Environment::class);

        return $relation;
    }

    /**
     * @return BelongsTo<Flag, static>
     */
    public function flag(): BelongsTo
    {
        /** @var BelongsTo<Flag, static> $relation */
        $relation = $this->belongsTo(Flag::class);

        return $relation;
    }
}
