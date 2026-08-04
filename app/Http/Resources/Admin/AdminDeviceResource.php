<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserDevice
 */
class AdminDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'username' => $this->whenLoaded('user', fn () => $this->user?->username),
            'name' => $this->name,
            'platform' => $this->platform?->value,
            'fingerprint' => $this->fingerprint,
            'status' => $this->status?->value,
            'approved_at' => $this->approved_at,
            'revoked_at' => $this->revoked_at,
            'last_seen_at' => $this->last_seen_at,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
            // public_key intentionally omitted from admin list (still metadata-safe if needed)
        ];
    }
}
