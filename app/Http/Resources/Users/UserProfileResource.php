<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserProfile
 */
class UserProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar' => $this->avatarMeta(),
            'avatar_url' => null,
            'locale' => $this->locale,
            'theme' => $this->theme?->value,
            'updated_at' => $this->updated_at,
        ];
    }
}
