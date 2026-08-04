<?php

namespace App\Actions\Devices;

use App\Actions\Sync\EnsureDeviceSyncStateAction;
use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Events\DeviceStatusChanged;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveDeviceAction
{
    public function __construct(
        private readonly EnsureDeviceSyncStateAction $ensureDeviceSyncStateAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(UserDevice $approver, UserDevice $target): UserDevice
    {
        if (! $approver->isApproved()) {
            throw ValidationException::withMessages([
                'device' => ['Only an approved device can approve another device.'],
            ]);
        }

        if ($approver->user_id !== $target->user_id) {
            throw ValidationException::withMessages([
                'device' => ['You cannot approve a device that does not belong to this account.'],
            ]);
        }

        if ($target->isRevoked()) {
            throw ValidationException::withMessages([
                'device' => ['A revoked device cannot be approved.'],
            ]);
        }

        if ($target->isApproved()) {
            $this->ensureDeviceSyncStateAction->execute($target);

            return $target;
        }

        $device = DB::transaction(function () use ($approver, $target) {
            $target->forceFill([
                'status' => DeviceStatus::Approved,
                'approved_at' => now(),
                'approved_by_device_id' => $approver->id,
            ])->save();

            $this->ensureDeviceSyncStateAction->execute($target->fresh());

            return $target->fresh();
        });

        $this->auditLogger->security(
            SecurityEventType::DeviceApproved,
            $approver->user,
            [
                'device_id' => $device->id,
                'approved_by_device_id' => $approver->id,
            ],
            deviceId: $approver->id,
        );

        DeviceStatusChanged::dispatch($device, 'approved');

        return $device;
    }
}
