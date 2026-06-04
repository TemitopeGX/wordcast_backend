<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'thumbnail',
        'excerpt',
        'content',
        'category',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    // ── Auto-generate slug ────────────────────────────────────────

    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }

    // ── Helpers ───────────────────────────────────────────────────

    /**
     * Returns a short preview (first ~120 chars of content stripped of HTML).
     */
    public function getPreviewAttribute(): string
    {
        $noNbsp = str_replace('&nbsp;', ' ', $this->content);
        $decoded = html_entity_decode($noNbsp, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded);
        $clean = trim(preg_replace('/\s+/u', ' ', $stripped));
        return Str::limit($clean, 120);
    }
}
