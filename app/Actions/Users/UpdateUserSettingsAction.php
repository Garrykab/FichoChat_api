<?php

namespace App\Actions\Users;

use App\Enums\ActivityLogType;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateUserSettingsAction
{
    public function __construct(
        private readonly EnsureUserSettingsAction $ensureUserSettingsAction,
        private readonly EnsureUserProfileAction $ensureUserProfileAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     notifications_enabled?: bool,
     *     notify_messages?: bool,
     *     notify_devices?: bool,
     *     notify_security?: bool,
     *     hide_message_previews?: bool,
     *     silent_mode?: bool,
     *     send_read_receipts?: bool,
     *     show_last_seen?: bool,
     *     auto_download_media?: bool,
     *     locale?: string,
     *     theme?: string
     * }  $data
     * @return array{settings: UserSetting, profile: UserProfile}
     */
    public function execute(User $user, array $data): array
    {
        $result = DB::transaction(function () use ($user, $data) {
            $settings = $this->ensureUserSettingsAction->execute($user);
            $profile = $this->ensureUserProfileAction->execute($user);

            $preferenceKeys = [
                'notifications_enabled',
                'notify_messages',
                'notify_devices',
                'notify_security',
                'hide_message_previews',
                'silent_mode',
                'send_read_receipts',
                'show_last_seen',
                'auto_download_media',
            ];

            $preferencePayload = array_intersect_key($data, array_flip($preferenceKeys));
            if ($preferencePayload !== []) {
                $settings->fill($preferencePayload)->save();
            }

            $profilePayload = array_intersect_key($data, array_flip(['locale', 'theme']));
            if ($profilePayload !== []) {
                $profile->fill($profilePayload)->save();
            }

            return [
                'settings' => $settings->fresh(),
                'profile' => $profile->fresh(),
            ];
        });

        $this->auditLogger->activity(
            ActivityLogType::SettingsUpdated,
            $user,
            ['keys' => array_keys($data)],
            UserSetting::class,
            $result['settings']->id,
        );

        return $result;
    }
}
