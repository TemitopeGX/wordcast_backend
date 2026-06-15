<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('name');
            $table->boolean('is_beta_tester')->default(false)->after('organization');
            $table->string('beta_source')->nullable()->after('is_beta_tester');
            $table->timestamp('beta_approved_at')->nullable()->after('beta_source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['organization', 'is_beta_tester', 'beta_source', 'beta_approved_at']);
        });
    }
};
