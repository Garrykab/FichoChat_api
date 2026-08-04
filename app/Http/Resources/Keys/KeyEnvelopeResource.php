<?php

namespace App\Http\Resources\Keys;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\KeyEnvelope */
class KeyEnvelopeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content_type' => $this->content_type?->value,
            'content_id' => $this->content_id,
            'sender_device_id' => $this->sender_device_id,
            'recipient_device_id' => $this->recipient_device_id,
            'encrypted_cek' => $this->encrypted_cek,
            'ephemeral_public_key' => $this->ephemeral_public_key,
            'iv' => $this->iv,
            'algorithm' => $this->algorithm,
            'key_version' => $this->key_version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
