<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseDevice extends Model
{
    protected $table = 'license_devices';

    protected $fillable = [
        'license_id', 'machine_id', 'license_hash',
        'device_name', 'os', 'activated_at', 'last_active_at',
    ];

    protected $casts = [
        'activated_at'  => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
