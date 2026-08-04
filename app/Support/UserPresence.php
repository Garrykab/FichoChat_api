<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserSetting;
use Carbon\CarbonInterface;

class UserPresence
{
    /** Fenêtre pendant laquelle un heartbeat compte comme « en ligne » côté API. */
    public const ONLINE_WINDOW_SECONDS = 75;

    public static function sharesPresence(User $user): bool
    {
        $settings = $user->relationLoaded('settings')
            ? $user->settings
            : UserSetting::query()->where('user_id', $user->id)->first();

        return $settings?->show_last_seen ?? true;
    }

    public static function isOnline(User $user): bool
    {
        if (! self::sharesPresence($user)) {
            return false;
        }

        $lastSeen = $user->last_seen_at;
        if (! $lastSeen instanceof CarbonInterface) {
            return false;
        }

        return $lastSeen->greaterThan(now()->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }

    /**
     * @return array{shares_presence: bool, is_online: bool|null, last_seen_at: string|null}
     */
    public static function toArray(User $user): array
    {
        if (! self::sharesPresence($user)) {
            return [
                'shares_presence' => false,
                'is_online' => null,
                'last_seen_at' => null,
            ];
        }

        return [
            'shares_presence' => true,
            'is_online' => self::isOnline($user),
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ];
    }
}
