<?php

namespace App\Http\Resources\Devices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeviceRecoveryBackup */
class RecoveryBackupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ciphertext' => $this->ciphertext,
            'salt' => $this->salt,
            'iv' => $this->iv,
            'kdf' => $this->kdf,
            'kdf_iterations' => $this->kdf_iterations,
            'public_key_fingerprint' => $this->public_key_fingerprint,
            'version' => $this->version,
            'updated_at' => $this->updated_at,
        ];
    }
}
