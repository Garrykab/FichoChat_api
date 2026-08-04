<?php

namespace App\Actions\Devices;

use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Models\DeviceRecoveryBackup;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpsertRecoveryBackupAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     ciphertext: string,
     *     salt: string,
     *     iv: string,
     *     kdf: string,
     *     kdf_iterations: int,
     *     public_key_fingerprint: string,
     *     version?: int
     * }  $data
     */
    public function execute(User $user, array $data, Request $request): DeviceRecoveryBackup
    {
        $approved = $user->devices()->where('status', DeviceStatus::Approved)->exists();
        if (! $approved) {
            throw ValidationException::withMessages([
                'device' => ['Seul un appareil approuvé peut enregistrer une phrase de récupération.'],
            ]);
        }

        $backup = DeviceRecoveryBackup::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'ciphertext' => $data['ciphertext'],
                'salt' => $data['salt'],
                'iv' => $data['iv'],
                'kdf' => $data['kdf'],
                'kdf_iterations' => $data['kdf_iterations'],
                'public_key_fingerprint' => $data['public_key_fingerprint'],
                'version' => $data['version'] ?? 1,
            ],
        );

        $this->auditLogger->security(
            SecurityEventType::DeviceRecoveryBackupUpdated,
            $user,
            [
                'fingerprint' => $backup->public_key_fingerprint,
                'version' => $backup->version,
            ],
            request: $request,
        );

        return $backup;
    }
}
