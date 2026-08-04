<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            $table->timestamp('profile_setup_completed_at')->nullable()->after('terms_accepted_at');
        });

        // Comptes existants : ne pas forcer le setup profil.
        DB::table('users')
            ->whereNull('profile_setup_completed_at')
            ->update([
                'terms_accepted_at' => DB::raw('COALESCE(terms_accepted_at, created_at)'),
                'profile_setup_completed_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['terms_accepted_at', 'profile_setup_completed_at']);
        });
    }
};
