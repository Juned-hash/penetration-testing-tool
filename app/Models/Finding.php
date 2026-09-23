<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finding extends Model
{
    protected $fillable = [
        'scan_id',
        'source',
        'external_id',
        'name',
        'risk',
        'confidence',
        'severity',
        'url',
        'method',
        'parameter',
        'attack',
        'evidence',
        'description',
        'impact',
        'solution',
        'reference',
        'cwe_id',
        'wasc_id',
        'wstg_id',
        'status',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
