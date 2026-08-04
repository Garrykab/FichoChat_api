<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class PrivateUserResource extends JsonResource
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
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
            'email_verified_at' => $this->email_verified_at,
            'profile_setup_completed' => $this->hasCompletedProfileSetup(),
            'profile' => new UserProfileResource($this->whenLoaded('profile', $this->profile)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
