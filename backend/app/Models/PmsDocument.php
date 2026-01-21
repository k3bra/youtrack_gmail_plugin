<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmsDocument extends Model
{
    protected $fillable = [
        'original_filename',
        'storage_path',
        'analysis_result',
    ];

    protected $casts = [
        'analysis_result' => 'array',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(PmsDocumentTicket::class);
    }
}
