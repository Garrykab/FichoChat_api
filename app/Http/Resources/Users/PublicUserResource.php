<?php

namespace App\Http\Resources\Users;

use App\Support\UserPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class PublicUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->whenLoaded('profile', $this->profile);
        $presence = UserPresence::toArray($this->resource);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'display_name' => $profile?->display_name ?? $this->username,
            'bio' => $profile?->bio,
            'avatar' => $profile?->avatarMeta(),
            'avatar_url' => null,
            'shares_presence' => $presence['shares_presence'],
            'is_online' => $presence['is_online'],
            'last_seen_at' => $presence['last_seen_at'],
        ];
    }
}
