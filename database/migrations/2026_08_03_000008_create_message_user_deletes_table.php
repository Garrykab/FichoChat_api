<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_user_deletes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('deleted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'uq_message_user_deletes');
            $table->index(['user_id', 'deleted_at'], 'idx_message_user_deletes_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_user_deletes');
    }
};
