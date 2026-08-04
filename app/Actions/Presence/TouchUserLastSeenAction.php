<?php

namespace App\Actions\Presence;

use App\Models\User;
use App\Models\UserSetting;

class TouchUserLastSeenAction
{
    /**
     * Met à jour last_seen_at uniquement si l’utilisateur partage sa présence.
     */
    public function execute(User $user): ?User
    {
        $settings = $user->relationLoaded('settings')
            ? $user->settings
            : UserSetting::query()->where('user_id', $user->id)->first();

        if ($settings !== null && $settings->show_last_seen === false) {
            return null;
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        return $user->fresh();
    }
}
