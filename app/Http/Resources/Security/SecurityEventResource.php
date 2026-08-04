<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SecurityEvent
 */
class SecurityEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'severity' => $this->severity?->value,
            'module' => $this->module,
            'result' => $this->result,
            'device_id' => $this->device_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'context' => $this->context,
            'created_at' => $this->created_at,
        ];
    }
}
