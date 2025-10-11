<?php

declare(strict_types=1);

namespace Phlag\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
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
        'action',
        'target_type',
        'target_id',
        'actor_type',
        'actor_identifier',
        'changes',
        'context',
        'occurred_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'changes' => 'array',
        'context' => 'array',
        'occurred_at' => 'datetime',
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
