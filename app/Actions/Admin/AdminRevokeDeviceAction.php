<?php

namespace App\Actions\Admin;

use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Events\DeviceStatusChanged;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRevokeDeviceAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $actor, UserDevice $device): UserDevice
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'device' => ['Admin access required.'],
            ]);
        }

        if ($device->isRevoked()) {
            return $device;
        }

        $revoked = DB::transaction(function () use ($device) {
            $device->forceFill([
                'status' => DeviceStatus::Revoked,
                'revoked_at' => now(),
            ])->save();

            $this->tokenService->revokeAllForDevice($device->id);

            return $device->fresh();
        });

        $this->auditLogger->security(
            SecurityEventType::AdminDeviceRevoked,
            $actor,
            [
                'device_id' => $revoked->id,
                'owner_user_id' => $revoked->user_id,
            ],
            module: 'admin',
        );

        DeviceStatusChanged::dispatch($revoked, 'revoked');

        return $revoked;
    }
}
