<?php

namespace App\Actions\Admin;

use App\Enums\DeviceStatus;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserDevice;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

class BuildAdminStatsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $adminRole = Role::findByName('admin', 'api');

        return [
            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', UserStatus::Active)->count(),
                'pending' => User::query()->where('status', UserStatus::Pending)->count(),
                'suspended' => User::query()->where('status', UserStatus::Suspended)->count(),
                'admins' => $adminRole ? $adminRole->users()->count() : 0,
            ],
            'devices' => [
                'total' => UserDevice::query()->count(),
                'approved' => UserDevice::query()->where('status', DeviceStatus::Approved)->count(),
                'pending' => UserDevice::query()->where('status', DeviceStatus::Pending)->count(),
                'revoked' => UserDevice::query()->where('status', DeviceStatus::Revoked)->count(),
            ],
            'conversations' => Conversation::query()->count(),
            'messages' => Message::withTrashed()->count(),
            'medias' => Media::query()->count(),
            'security_events_24h' => SecurityEvent::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'activity_logs_24h' => Activity::query()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'login_failures_24h' => SecurityEvent::query()
                ->where('type', 'login_failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'generated_at' => now()->toISOString(),
        ];
    }
}
