<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procontent_assets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category'); // backgrounds, motion_loops, overlays, gradients, videos
            $table->string('type');     // image or video
            $table->string('r2_key');   // the R2 object key e.g. media/backgrounds/sunset.jpg
            $table->string('cdn_url');  // full public CDN URL
            $table->string('thumbnail_url'); // URL to a smaller preview version
            $table->string('filename');
            $table->unsignedBigInteger('file_size'); // bytes
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('uploaded_by'); // admin user email
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procontent_assets');
    }
};
