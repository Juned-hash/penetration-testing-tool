<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthenticationConfiguration extends Model
{
    protected $fillable = [
        'scan_id',
        'mode',
        'login_url',
        'username_field',
        'password_field',
        'username',
        'password',
        'token_name',
        'token_value',
        'login_button_selector',
        'logged_in_indicator',
        'logged_out_indicator',
        'authenticated_url',
        'additional_configuration',
    ];

    protected $hidden = [
        'password',
        'token_value',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'token_value' => 'encrypted',
        'additional_configuration' => 'array',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
