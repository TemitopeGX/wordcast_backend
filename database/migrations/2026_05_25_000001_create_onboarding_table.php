<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // The four tracked steps
            $table->boolean('account_created')->default(true); // always true if row exists
            $table->boolean('app_downloaded')->default(false);
            $table->boolean('app_logged_in')->default(false);
            $table->boolean('cloud_sync_setup')->default(false);

            $table->timestamps();

            $table->unique('user_id'); // one row per user
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding');
    }
};
