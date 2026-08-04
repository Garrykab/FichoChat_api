<?php

namespace App\Actions\Admin;

use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\TokenService;
use Illuminate\Validation\ValidationException;

class SuspendUserAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $actor, User $target, ?string $reason = null): User
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['Admin access required.'],
            ]);
        }

        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot suspend your own account.'],
            ]);
        }

        if ($target->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['Cannot suspend another administrator.'],
            ]);
        }

        if ($target->status === UserStatus::Suspended) {
            return $target;
        }

        $target->forceFill(['status' => UserStatus::Suspended])->save();
        $this->tokenService->revokeAllForUser($target);

        $this->auditLogger->security(
            SecurityEventType::UserSuspended,
            $actor,
            [
                'target_user_id' => $target->id,
                'reason' => $reason,
            ],
            module: 'admin',
        );

        return $target->fresh();
    }
}
