<?php

namespace App\Actions\Sync;

use App\Enums\SyncStatus;
use App\Models\DeviceSyncState;
use App\Models\UserDevice;

class EnsureDeviceSyncStateAction
{
    public function execute(UserDevice $device): DeviceSyncState
    {
        return DeviceSyncState::query()->firstOrCreate(
            ['device_id' => $device->id],
            [
                'user_id' => $device->user_id,
                'status' => SyncStatus::PendingBootstrap,
            ],
        );
    }
}
