<?php

declare(strict_types=1);

namespace Phlag\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class);
    }
}
