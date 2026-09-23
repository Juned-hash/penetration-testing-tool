<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanConfiguration extends Model
{
    protected $fillable = [
        'scan_id',
        'spider_enabled',
        'ajax_spider_enabled',
        'passive_scan_enabled',
        'active_scan_enabled',
        'authentication_enabled',
        'options',
    ];

    protected $casts = [
        'spider_enabled' => 'boolean',
        'ajax_spider_enabled' => 'boolean',
        'passive_scan_enabled' => 'boolean',
        'active_scan_enabled' => 'boolean',
        'authentication_enabled' => 'boolean',
        'options' => 'array',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
