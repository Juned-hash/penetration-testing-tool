<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'target_url',
        'environment',
        'status',
        'authorization_confirmed_at',
        'started_at',
        'completed_at',
        'failure_reason',
    ];

    protected $casts = [
        'authorization_confirmed_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanConfiguration(): HasOne
    {
        return $this->hasOne(ScanConfiguration::class);
    }

    public function scanScopes(): HasMany
    {
        return $this->hasMany(ScanScope::class);
    }

    public function authenticationConfiguration(): HasOne
    {
        return $this->hasOne(AuthenticationConfiguration::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }
}
