<?php

namespace App\Http\Resources\Media;

use App\Services\Settings\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UploadSession */
class UploadSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $chunkSize = app(AppSettings::class)->mediaLimits()['chunk_bytes'];

        return [
            'id' => $this->id,
            'media_id' => $this->media_id,
            'status' => $this->status?->value,
            'bytes_received' => $this->bytes_received,
            'chunk_size' => $chunkSize,
            'expires_at' => $this->expires_at?->toISOString(),
            'upload_url' => url('/api/v1/media/sessions/'.$this->id.'/content'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
