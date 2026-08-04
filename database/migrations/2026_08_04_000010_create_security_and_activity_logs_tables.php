<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('user_devices')->nullOnDelete();
            $table->string('type', 64);
            $table->string('severity', 16)->default('info');
            $table->string('module', 64)->nullable();
            $table->string('result', 32)->default('success');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_security_events_user_created');
            $table->index(['type', 'created_at'], 'idx_security_events_type_created');
            $table->index(['severity', 'created_at'], 'idx_security_events_severity_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
