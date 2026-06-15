<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('organization')->nullable();
            $table->string('source')->default('website');
            $table->enum('status', [
                'pending',
                'approved',
                'registered',
                'rejected',
            ])->default('pending');
            $table->string('invite_token', 128)->nullable()->unique();
            $table->timestamp('invite_sent_at')->nullable();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('invite_clicked_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('invite_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist');
    }
};
