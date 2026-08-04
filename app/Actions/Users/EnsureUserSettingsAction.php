<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Facades\DB;

class EnsureUserSettingsAction
{
    public function execute(User $user): UserSetting
    {
        return DB::transaction(function () use ($user) {
            return UserSetting::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'notifications_enabled' => true,
                    'notify_messages' => true,
                    'notify_devices' => true,
                    'notify_security' => true,
                    'hide_message_previews' => false,
                    'silent_mode' => false,
                    'send_read_receipts' => true,
                    'show_last_seen' => true,
                    'auto_download_media' => false,
                ],
            );
        });
    }
}
