<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdminCommand extends Command
{
    protected $signature = 'admin:promote {email : Email du compte à promouvoir}';

    protected $description = 'Promouvoir un utilisateur au rôle Spatie admin';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Utilisateur introuvable : {$email}");

            return self::FAILURE;
        }

        $user->syncRoles(['admin']);
        $this->info("{$user->username} ({$user->email}) est maintenant admin (Spatie).");

        return self::SUCCESS;
    }
}
