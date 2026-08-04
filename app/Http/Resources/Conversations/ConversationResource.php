<?php

namespace App\Http\Resources\Conversations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authId = $request->user()?->id;
        $myParticipation = null;

        if ($this->relationLoaded('participants') && $authId !== null) {
            $myParticipation = $this->participants->firstWhere('user_id', $authId);
        }

        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'created_by_user_id' => $this->created_by_user_id,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'my_status' => $myParticipation?->status?->value,
            'my_role' => $myParticipation?->role?->value,
            'participants' => ConversationParticipantResource::collection($this->whenLoaded('participants')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
