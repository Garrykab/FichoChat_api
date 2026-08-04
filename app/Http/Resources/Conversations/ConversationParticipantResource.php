<?php

namespace App\Http\Resources\Conversations;

use App\Http\Resources\Users\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ConversationParticipant */
class ConversationParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'role' => $this->role?->value,
            'status' => $this->status?->value,
            'archived_at' => $this->archived_at?->toISOString(),
            'hidden_at' => $this->hidden_at?->toISOString(),
            'last_read_at' => $this->last_read_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => new PublicUserResource($this->user)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
