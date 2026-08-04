<?php

namespace App\Http\Resources\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Media */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'message_id' => $this->message_id,
            'uploader_user_id' => $this->uploader_user_id,
            'uploader_device_id' => $this->uploader_device_id,
            'type' => $this->type?->value,
            'mime_type' => $this->mime_type,
            'original_filename' => $this->original_filename,
            'size_bytes' => $this->size_bytes,
            'encrypted_size_bytes' => $this->encrypted_size_bytes,
            'checksum_sha256' => $this->checksum_sha256,
            'content_iv' => $this->content_iv,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
