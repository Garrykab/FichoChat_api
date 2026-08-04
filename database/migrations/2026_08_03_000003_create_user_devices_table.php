<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform', 32)->index();
            $table->text('public_key');
            $table->string('fingerprint', 64)->index();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by_device_id')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->foreign('approved_by_device_id')
                ->references('id')
                ->on('user_devices')
                ->nullOnDelete();
        });

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->foreign('device_id')
                ->references('id')
                ->on('user_devices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropForeign(['approved_by_device_id']);
        });

        Schema::dropIfExists('user_devices');
    }
};
