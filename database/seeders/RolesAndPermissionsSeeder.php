<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const GUARD = 'api';

    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'admin.access',
        'admin.stats.view',
        'admin.users.view',
        'admin.users.suspend',
        'admin.devices.view',
        'admin.devices.revoke',
        'admin.logs.view',
        'admin.settings.view',
        'admin.settings.update',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        $user = Role::findOrCreate('user', self::GUARD);
        $admin = Role::findOrCreate('admin', self::GUARD);

        $admin->syncPermissions(self::PERMISSIONS);
        $user->syncPermissions([]);
    }
}
