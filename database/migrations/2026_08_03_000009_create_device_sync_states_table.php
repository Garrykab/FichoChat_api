<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sync_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->string('status', 32)->default('pending_bootstrap');
            $table->timestamp('cursor_at')->nullable();
            $table->uuid('last_message_id')->nullable();
            $table->timestamp('bootstrap_completed_at')->nullable();
            $table->timestamps();

            $table->unique('device_id', 'uq_device_sync_states_device');
            $table->index(['user_id', 'status'], 'idx_device_sync_states_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sync_states');
    }
};
