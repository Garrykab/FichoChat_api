<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_logs');

        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite : drop index then rebuild without role
            try {
                DB::statement('DROP INDEX IF EXISTS users_role_index');
            } catch (\Throwable) {
                // ignore
            }

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('role');
            });

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('user')->after('status')->index();
        });
    }
};
