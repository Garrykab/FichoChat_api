<?php

namespace App\Http\Resources\Keys;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserDevice */
class DevicePublicKeyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'platform' => $this->platform?->value,
            'public_key' => $this->public_key,
            'fingerprint' => $this->fingerprint,
            'status' => $this->status?->value,
        ];
    }
}
