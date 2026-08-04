<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medias', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignUuid('uploader_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('uploader_device_id')->constrained('user_devices')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('mime_type', 127);
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('encrypted_size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('storage_disk', 32)->default('media');
            $table->string('storage_path')->nullable();
            $table->string('content_iv')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'status'], 'idx_medias_conversation_status');
            $table->index(['message_id'], 'idx_medias_message');
        });

        Schema::create('upload_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_id')->constrained('medias')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('open');
            $table->unsignedBigInteger('bytes_received')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['media_id', 'status'], 'idx_upload_sessions_media_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
        Schema::dropIfExists('medias');
    }
};
