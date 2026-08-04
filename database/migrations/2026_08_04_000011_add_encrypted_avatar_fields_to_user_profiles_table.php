<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('avatar_disk', 32)->nullable()->after('avatar_path');
            $table->string('avatar_content_iv')->nullable()->after('avatar_disk');
            $table->string('avatar_mime_type', 127)->nullable()->after('avatar_content_iv');
            $table->string('avatar_checksum_sha256', 64)->nullable()->after('avatar_mime_type');
            $table->unsignedBigInteger('avatar_size_bytes')->default(0)->after('avatar_checksum_sha256');
            $table->unsignedBigInteger('avatar_encrypted_size_bytes')->default(0)->after('avatar_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'avatar_disk',
                'avatar_content_iv',
                'avatar_mime_type',
                'avatar_checksum_sha256',
                'avatar_size_bytes',
                'avatar_encrypted_size_bytes',
            ]);
        });
    }
};
