<?php

namespace App\Actions\Admin;

use App\Models\User;

class PromoteAdminFromConfigAction
{
    public function execute(User $user): User
    {
        $emails = config('fichochat.admin_emails', []);

        if ($emails === [] || $user->isAdmin()) {
            return $user;
        }

        $normalized = array_map('strtolower', $emails);

        if (in_array(strtolower($user->email), $normalized, true)) {
            $user->syncRoles(['admin']);
        }

        return $user->fresh() ?? $user;
    }
}
