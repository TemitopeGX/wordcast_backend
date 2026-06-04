<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Licenses ─────────────────────────────────────────────────────────
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('license_key', 64)->unique();          // WCL-UUID format
            $table->string('license_hash', 64)->nullable();        // SHA-256 for quick lookup
            $table->enum('plan', ['free', 'pro', 'campus'])->default('free');
            $table->unsignedSmallInteger('seat_limit')->default(1);
            $table->timestamp('expires_at')->nullable();           // null = lifetime / free
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Devices ───────────────────────────────────────────────────────────
        Schema::create('license_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->onDelete('cascade');
            $table->string('machine_id', 128)->index();
            $table->string('license_hash', 64)->index();           // hashed per device
            $table->string('device_name', 128)->nullable();
            $table->string('os', 32)->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('last_active_at')->nullable();
            $table->unique(['license_id', 'machine_id']);
            $table->timestamps();
        });

        // ── App tokens (short-lived OAuth tokens for desktop OAuth flow) ──────
        Schema::create('app_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token', 128)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // ── Subscriptions ─────────────────────────────────────────────────────
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('license_id')->constrained()->onDelete('cascade');
            $table->string('paystack_subscription_code')->nullable()->unique();
            $table->string('paystack_customer_code')->nullable();
            $table->enum('status', ['active', 'cancelled', 'expired'])->default('active');
            $table->decimal('amount', 10, 2)->default(5000);
            $table->string('currency', 3)->default('NGN');
            $table->timestamp('next_payment_date')->nullable();
            $table->timestamps();
        });

        // ── Payments ──────────────────────────────────────────────────────────
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('set null');
            $table->string('paystack_reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('app_tokens');
        Schema::dropIfExists('license_devices');
        Schema::dropIfExists('licenses');
    }
};
