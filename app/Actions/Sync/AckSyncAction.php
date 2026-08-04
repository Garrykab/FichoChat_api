<?php

namespace App\Actions\Sync;

use App\Enums\ActivityLogType;
use App\Enums\SyncStatus;
use App\Models\DeviceSyncState;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AckSyncAction
{
    public function __construct(
        private readonly EnsureDeviceSyncStateAction $ensureDeviceSyncStateAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     device_id: string,
     *     cursor_at: string,
     *     last_message_id?: string|null,
     *     bootstrap_completed?: bool
     * }  $data
     */
    public function execute(User $user, array $data): DeviceSyncState
    {
        $device = UserDevice::query()->find($data['device_id']);

        if ($device === null || $device->user_id !== $user->id || ! $device->isApproved()) {
            throw ValidationException::withMessages([
                'device_id' => ['Device must be an approved device of the authenticated user.'],
            ]);
        }

        $state = $this->ensureDeviceSyncStateAction->execute($device);
        $cursorAt = CarbonImmutable::parse($data['cursor_at']);

        $payload = [
            'cursor_at' => $cursorAt,
            'last_message_id' => $data['last_message_id'] ?? $state->last_message_id,
        ];

        if (($data['bootstrap_completed'] ?? false) === true || $state->bootstrap_completed_at !== null) {
            $payload['bootstrap_completed_at'] = $state->bootstrap_completed_at ?? now();
            $payload['status'] = SyncStatus::Ready;
        }

        $state->forceFill($payload)->save();

        $fresh = $state->fresh();

        $this->auditLogger->activity(
            ActivityLogType::SyncAck,
            $user,
            [
                'cursor_at' => $fresh->cursor_at?->toISOString(),
                'bootstrap_completed' => $fresh->bootstrap_completed_at !== null,
            ],
            DeviceSyncState::class,
            $fresh->id,
            deviceId: $device->id,
        );

        return $fresh;
    }
}
