<?php

namespace App\Services\Audit;

use App\Enums\ActivityLogType;
use App\Enums\LogSeverity;
use App\Enums\SecurityEventType;
use App\Jobs\Audit\PersistActivityLogJob;
use App\Jobs\Audit\PersistSecurityEventJob;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function security(
        SecurityEventType $type,
        ?User $user = null,
        array $context = [],
        LogSeverity $severity = LogSeverity::Info,
        string $result = 'success',
        ?string $deviceId = null,
        ?string $module = null,
        ?Request $request = null,
    ): SecurityEvent {
        $request ??= request();
        $safeContext = $this->sanitizeContext($context);

        $attributes = [
            'user_id' => $user?->id,
            'device_id' => $deviceId,
            'type' => $type,
            'severity' => $severity,
            'module' => $module ?? $this->moduleFromSecurityType($type),
            'result' => $result,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? (string) $request->userAgent() : null,
            'context' => $safeContext === [] ? null : $safeContext,
        ];

        PersistSecurityEventJob::dispatch($attributes);

        return new SecurityEvent($attributes);
    }

    /**
     * Journal métier via spatie/laravel-activitylog (écriture asynchrone).
     *
     * @param  array<string, mixed>  $context
     */
    public function activity(
        ActivityLogType $type,
        ?User $user = null,
        array $context = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
        LogSeverity $severity = LogSeverity::Info,
        string $result = 'success',
        ?string $deviceId = null,
        ?string $module = null,
        ?Request $request = null,
    ): Activity {
        $request ??= request();
        $safeContext = $this->sanitizeContext($context);
        $logName = $module ?? $this->moduleFromActivityType($type);

        $properties = [
            'context' => $safeContext === [] ? null : $safeContext,
            'device_id' => $deviceId,
            'result' => $result,
            'severity' => $severity->value,
            'module' => $logName,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? (string) $request->userAgent() : null,
        ];

        PersistActivityLogJob::dispatch(
            $logName,
            $type->value,
            $type->value,
            $user?->id,
            $subjectType,
            $subjectId,
            $properties,
        );

        return new Activity([
            'log_name' => $logName,
            'description' => $type->value,
            'event' => $type->value,
            'properties' => $properties,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $blocked = [
            'password',
            'password_confirmation',
            'ciphertext',
            'encrypted_cek',
            'private_key',
            'public_key',
            'refresh_token',
            'access_token',
            'token',
            'cek',
            'iv',
            'ephemeral_public_key',
        ];

        $clean = [];

        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);

            if (in_array($normalized, $blocked, true)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeContext($value);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function moduleFromSecurityType(SecurityEventType $type): string
    {
        return match ($type) {
            SecurityEventType::DeviceRegistered,
            SecurityEventType::DeviceApproved,
            SecurityEventType::DeviceRevoked,
            SecurityEventType::AdminDeviceRevoked => 'devices',
            SecurityEventType::LogsViewed,
            SecurityEventType::UserSuspended,
            SecurityEventType::UserActivated => 'admin',
            default => 'auth',
        };
    }

    private function moduleFromActivityType(ActivityLogType $type): string
    {
        return match ($type) {
            ActivityLogType::ConversationCreated,
            ActivityLogType::ConversationArchived,
            ActivityLogType::ConversationHidden => 'conversations',
            ActivityLogType::MessageSent,
            ActivityLogType::MessageUpdated,
            ActivityLogType::MessageDeleted,
            ActivityLogType::MessageDeletedForMe => 'messages',
            ActivityLogType::MediaUploaded => 'media',
            ActivityLogType::ProfileUpdated => 'users',
            ActivityLogType::SettingsUpdated => 'settings',
            ActivityLogType::SyncBootstrap,
            ActivityLogType::SyncAck => 'sync',
        };
    }
}
