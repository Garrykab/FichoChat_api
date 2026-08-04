<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_envelopes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('content_type', 32);
            $table->uuid('content_id');
            $table->foreignUuid('sender_device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->foreignUuid('recipient_device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->text('encrypted_cek');
            $table->text('ephemeral_public_key');
            $table->string('iv');
            $table->string('algorithm', 64)->default('ECDH-P256+HKDF-SHA256+AES-256-GCM');
            $table->unsignedInteger('key_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['content_type', 'content_id', 'recipient_device_id'],
                'uq_key_envelopes_content_recipient',
            );
            $table->index(['recipient_device_id', 'created_at'], 'idx_key_envelopes_recipient_created');
            $table->index(['content_type', 'content_id'], 'idx_key_envelopes_content');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_envelopes');
    }
};
