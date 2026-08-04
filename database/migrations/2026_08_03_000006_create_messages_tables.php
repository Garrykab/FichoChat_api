<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('sender_device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->string('type', 32)->default('text');
            $table->text('ciphertext');
            $table->string('iv');
            $table->uuid('reply_to_message_id')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_for_everyone_at')->nullable();
            $table->foreignUuid('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at'], 'idx_messages_conversation_created_at');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->foreign('reply_to_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });

        Schema::create('message_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('user_devices')->nullOnDelete();
            $table->string('status', 32)->default('sent');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'uq_message_receipts_message_user');
            $table->index(['user_id', 'status'], 'idx_message_receipts_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_receipts');
        Schema::dropIfExists('messages');
    }
};
