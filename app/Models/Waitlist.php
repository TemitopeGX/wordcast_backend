<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Waitlist extends Model
{
    protected $table = 'waitlist';

    protected $fillable = [
        'name',
        'email',
        'organization',
        'source',
        'status',
        'invite_token',
        'invite_sent_at',
        'invite_expires_at',
        'invite_clicked_at',
        'registered_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'invite_sent_at'    => 'datetime',
        'invite_expires_at' => 'datetime',
        'invite_clicked_at' => 'datetime',
        'registered_at'     => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRegistered(Builder $query): Builder
    {
        return $query->where('status', 'registered');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->invite_expires_at !== null && now()->isAfter($this->invite_expires_at);
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
