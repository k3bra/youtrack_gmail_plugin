<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmsDocumentTicket extends Model
{
    protected $fillable = [
        'pms_document_id',
        'issue_id',
        'issue_url',
        'issue_status',
        'issue_type',
    ];
}
