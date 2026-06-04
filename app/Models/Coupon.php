<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'currency',
        'max_uses', 'uses_count', 'expires_at',
        'is_active', 'applicable_plans',
    ];

    protected $casts = [
        'applicable_plans' => 'array',
        'is_active'        => 'boolean',
        'expires_at'       => 'date',
        'value'            => 'decimal:2',
    ];
}
