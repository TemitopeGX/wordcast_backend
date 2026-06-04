<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProContentAsset extends Model
{
    protected $table = 'procontent_assets';

    protected $fillable = [
        'title', 'category', 'type', 'r2_key',
        'cdn_url', 'thumbnail_url', 'filename',
        'file_size', 'tags', 'uploaded_by', 'is_active',
    ];

    protected $casts = [
        'tags'      => 'array',
        'is_active' => 'boolean',
        'file_size' => 'integer',
    ];
}
