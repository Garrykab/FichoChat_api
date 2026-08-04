<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class EnsureUserProfileAction
{
    public function execute(User $user): UserProfile
    {
        return DB::transaction(function () use ($user) {
            return UserProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $user->username,
                    'locale' => 'fr',
                    'theme' => 'system',
                ],
            );
        });
    }
}
