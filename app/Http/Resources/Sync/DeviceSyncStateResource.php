<?php

namespace App\Http\Resources\Sync;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DeviceSyncState */
class DeviceSyncStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_id' => $this->device_id,
            'status' => $this->status?->value,
            'cursor_at' => $this->cursor_at?->toISOString(),
            'last_message_id' => $this->last_message_id,
            'bootstrap_completed_at' => $this->bootstrap_completed_at?->toISOString(),
            'needs_bootstrap' => $this->needsBootstrap(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
