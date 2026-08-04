<?php

namespace App\Actions\Admin;

use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class ActivateUserAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $actor, User $target): User
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'user' => ['Admin access required.'],
            ]);
        }

        if ($target->status === UserStatus::Active) {
            return $target;
        }

        // Pending → Active only if email verified; otherwise keep pending after unsuspend intent
        $next = $target->hasVerifiedEmail()
            ? UserStatus::Active
            : UserStatus::Pending;

        $target->forceFill(['status' => $next])->save();

        $this->auditLogger->security(
            SecurityEventType::UserActivated,
            $actor,
            [
                'target_user_id' => $target->id,
                'new_status' => $next->value,
            ],
            module: 'admin',
        );

        return $target->fresh();
    }
}
