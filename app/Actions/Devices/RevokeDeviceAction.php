<?php

namespace App\Actions\Devices;

use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Events\DeviceStatusChanged;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeDeviceAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(UserDevice $actor, UserDevice $target): UserDevice
    {
        if ($actor->user_id !== $target->user_id) {
            throw ValidationException::withMessages([
                'device' => ['You cannot revoke a device that does not belong to this account.'],
            ]);
        }

        if (! $actor->isApproved() && $actor->id !== $target->id) {
            throw ValidationException::withMessages([
                'device' => ['Only an approved device can revoke another device.'],
            ]);
        }

        if ($target->isRevoked()) {
            return $target;
        }

        $device = DB::transaction(function () use ($target) {
            $target->forceFill([
                'status' => DeviceStatus::Revoked,
                'revoked_at' => now(),
            ])->save();

            $this->tokenService->revokeAllForDevice($target->id);

            return $target->fresh();
        });

        $this->auditLogger->security(
            SecurityEventType::DeviceRevoked,
            $actor->user,
            [
                'device_id' => $device->id,
                'revoked_by_device_id' => $actor->id,
            ],
            deviceId: $actor->id,
        );

        DeviceStatusChanged::dispatch($device, 'revoked');

        return $device;
    }
}
