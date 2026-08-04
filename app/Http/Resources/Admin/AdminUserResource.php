<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status?->value,
            'role' => $this->primaryRoleName(),
            'roles' => $this->getRoleNames()->values()->all(),
            'email_verified_at' => $this->email_verified_at,
            'devices_count' => $this->whenCounted('devices'),
            'display_name' => $this->whenLoaded('profile', fn () => $this->profile?->display_name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
