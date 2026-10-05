<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TicketTriage extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_AI_FIX = 'ai-fix';

    protected $fillable = [
        'issue_id',
        'trigger',
        'status',
        'verdict',
        'confidence',
        'analysis',
        'session_id',
        'model',
        'cost_usd',
        'duration_ms',
        'num_turns',
        'error',
    ];

    protected $casts = [
        'analysis' => 'array',
        'cost_usd' => 'float',
        'duration_ms' => 'integer',
        'num_turns' => 'integer',
    ];

    public function latestFix(): HasOne
    {
        return $this->hasOne(TicketFix::class)->latestOfMany();
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'issue_id' => $this->issue_id,
            'trigger' => $this->trigger,
            'status' => $this->status,
            'verdict' => $this->verdict,
            'confidence' => $this->confidence,
            'analysis' => $this->analysis,
            'model' => $this->model,
            'cost_usd' => $this->cost_usd,
            'duration_ms' => $this->duration_ms,
            'num_turns' => $this->num_turns,
            'error' => $this->error,
            'fix' => $this->latestFix?->toApiArray(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
