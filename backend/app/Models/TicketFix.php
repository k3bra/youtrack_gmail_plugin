<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFix extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    // Draft PR open.
    public const STATUS_COMPLETED = 'completed';
    // Claude stopped on a gate while implementing; nothing was pushed.
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ticket_triage_id',
        'issue_id',
        'status',
        'repo',
        'branch',
        'commit_sha',
        'pr_url',
        'result',
        'model',
        'cost_usd',
        'duration_ms',
        'num_turns',
        'error',
    ];

    protected $casts = [
        'result' => 'array',
        'cost_usd' => 'float',
        'duration_ms' => 'integer',
        'num_turns' => 'integer',
    ];

    public function triage(): BelongsTo
    {
        return $this->belongsTo(TicketTriage::class, 'ticket_triage_id');
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'repo' => $this->repo,
            'branch' => $this->branch,
            'commit_sha' => $this->commit_sha,
            'pr_url' => $this->pr_url,
            'result' => $this->result,
            'model' => $this->model,
            'cost_usd' => $this->cost_usd,
            'duration_ms' => $this->duration_ms,
            'num_turns' => $this->num_turns,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
