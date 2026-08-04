<?php

namespace App\Http\Resources\Devices;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserDevice
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform?->value,
            'fingerprint' => $this->fingerprint,
            'public_key' => $this->public_key,
            'status' => $this->status?->value,
            'approved_at' => $this->approved_at,
            'approved_by_device_id' => $this->approved_by_device_id,
            'revoked_at' => $this->revoked_at,
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
