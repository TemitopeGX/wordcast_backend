<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Onboarding extends Model
{
    protected $table = 'onboarding';

    protected $fillable = [
        'user_id',
        'account_created',
        'app_downloaded',
        'app_logged_in',
        'cloud_sync_setup',
    ];

    protected $casts = [
        'account_created'  => 'boolean',
        'app_downloaded'   => 'boolean',
        'app_logged_in'    => 'boolean',
        'cloud_sync_setup' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
