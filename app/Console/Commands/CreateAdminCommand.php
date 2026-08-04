<?php

namespace App\Console\Commands;

use App\Actions\Users\EnsureUserProfileAction;
use App\Actions\Users\EnsureUserSettingsAction;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
                            {--email=admin@fichochat.local : Email du compte}
                            {--username=admin : Nom d’utilisateur}
                            {--password=Admin123! : Mot de passe}
                            {--name=Administrateur : Nom d’affichage}';

    protected $description = 'Créer un compte administrateur (actif, email vérifié, profil complété)';

    public function handle(
        EnsureUserProfileAction $ensureUserProfileAction,
        EnsureUserSettingsAction $ensureUserSettingsAction,
    ): int {
        $email = (string) $this->option('email');
        $username = (string) $this->option('username');
        $password = (string) $this->option('password');
        $name = (string) $this->option('name');

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Un compte existe déjà avec l’email : {$email}");

            return self::FAILURE;
        }

        if (User::query()->where('username', $username)->exists()) {
            $this->error("Un compte existe déjà avec le username : {$username}");

            return self::FAILURE;
        }

        Role::findOrCreate('admin', RolesAndPermissionsSeeder::GUARD);
        Role::findOrCreate('user', RolesAndPermissionsSeeder::GUARD);

        $user = DB::transaction(function () use (
            $email,
            $username,
            $password,
            $name,
            $ensureUserProfileAction,
            $ensureUserSettingsAction,
        ) {
            $user = User::query()->create([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'status' => UserStatus::Active,
                'terms_accepted_at' => now(),
                'profile_setup_completed_at' => now(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $profile = $ensureUserProfileAction->execute($user);
            $profile->forceFill(['display_name' => $name])->save();
            $ensureUserSettingsAction->execute($user);
            $user->syncRoles(['admin']);

            return $user->fresh(['profile']);
        });

        $this->info('Compte admin créé.');
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['ID', $user->id],
                ['Email', $user->email],
                ['Username', $user->username],
                ['Mot de passe', $password],
                ['Rôle', 'admin'],
                ['Status', $user->status->value],
            ],
        );

        return self::SUCCESS;
    }
}
