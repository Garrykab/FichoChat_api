<?php

namespace App\Actions\Devices;

use App\Enums\SecurityEventType;
use App\Models\DeviceRecoveryBackup;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class DeleteRecoveryBackupAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $user, Request $request): bool
    {
        $deleted = DeviceRecoveryBackup::query()->where('user_id', $user->id)->delete() > 0;

        if ($deleted) {
            $this->auditLogger->security(
                SecurityEventType::DeviceRecoveryBackupDeleted,
                $user,
                [],
                request: $request,
            );
        }

        return $deleted;
    }
}
