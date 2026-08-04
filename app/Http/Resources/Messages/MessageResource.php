<?php

namespace App\Http\Resources\Messages;

use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Users\PublicUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deleted = $this->isDeletedForEveryone() || $this->trashed();

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_user_id' => $this->sender_user_id,
            'sender_device_id' => $this->sender_device_id,
            'type' => $this->type?->value,
            'ciphertext' => $deleted ? null : $this->ciphertext,
            'iv' => $deleted ? null : $this->iv,
            'reply_to_message_id' => $this->reply_to_message_id,
            'edited_at' => $this->edited_at?->toISOString(),
            'deleted_for_everyone' => $deleted,
            'sender' => $this->whenLoaded('sender', fn () => new PublicUserResource($this->sender)),
            'receipts' => MessageReceiptResource::collection($this->whenLoaded('receipts')),
            'medias' => MediaResource::collection($this->whenLoaded('medias')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
