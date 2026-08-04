<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_recovery_backups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('ciphertext');
            $table->string('salt', 128);
            $table->string('iv', 64);
            $table->string('kdf', 32)->default('pbkdf2-sha256');
            $table->unsignedInteger('kdf_iterations')->default(310000);
            $table->string('public_key_fingerprint', 64);
            $table->unsignedTinyInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_recovery_backups');
    }
};
