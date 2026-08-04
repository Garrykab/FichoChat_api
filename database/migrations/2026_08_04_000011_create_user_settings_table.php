<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Notifications (PRD §23.9)
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('notify_messages')->default(true);
            $table->boolean('notify_devices')->default(true);
            $table->boolean('notify_security')->default(true);
            $table->boolean('hide_message_previews')->default(false);
            $table->boolean('silent_mode')->default(false);

            // Confidentialité
            $table->boolean('send_read_receipts')->default(true);
            $table->boolean('show_last_seen')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
