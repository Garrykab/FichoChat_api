<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 32)->default('private');
            $table->foreignUuid('created_by_user_id')->constrained('users')->cascadeOnDelete();
            /** Clé stable pour unicité 1-1 : min(uuid):max(uuid) */
            $table->string('pair_key', 73)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'last_message_at'], 'idx_conversations_type_last_message');
        });

        // Unicité 1-1 uniquement sur les conversations non soft-deleted
        Schema::getConnection()->getDriverName() === 'pgsql'
            ? Schema::getConnection()->statement(
                'CREATE UNIQUE INDEX uq_conversations_pair_key_active ON conversations (pair_key) WHERE deleted_at IS NULL AND pair_key IS NOT NULL',
            )
            : Schema::table('conversations', function (Blueprint $table): void {
                $table->unique('pair_key', 'uq_conversations_pair_key');
            });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('member');
            $table->string('status', 32)->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'uq_conversation_participants_pair');
            $table->index(['user_id', 'status', 'archived_at'], 'idx_participants_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
