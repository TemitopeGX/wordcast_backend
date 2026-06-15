<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'church_name', 'avatar_url',
        'organization', 'is_beta_tester', 'beta_source', 'beta_approved_at',
        'role', 'plan', 'plan_grace_until', 'status', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'plan_grace_until'  => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function license(): HasOne
    {
        return $this->hasOne(License::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function appTokens(): HasMany
    {
        return $this->hasMany(AppToken::class);
    }
}
