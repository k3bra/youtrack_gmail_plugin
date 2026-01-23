<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsDocumentTicket extends Model
{
    protected $fillable = [
        'pms_document_id',
        'issue_id',
        'issue_url',
        'issue_status',
        'issue_type',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PmsDocument::class, 'pms_document_id');
    }
}
