<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketRequest extends Model
{
    protected $fillable = [
        'request_type',
        'email_subject',
        'email_from',
        'email_body',
        'email_thread_url',
        'ai_summary',
        'ai_description',
        'ai_labels',
        'youtrack_issue_id',
        'status',
        'error_message',
    ];

    protected $casts = [
        'ai_labels' => 'array',
    ];
}
