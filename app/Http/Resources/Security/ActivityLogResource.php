<?php

namespace App\Http\Resources\Security;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Spatie\Activitylog\Models\Activity
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $properties = $this->properties?->toArray() ?? [];

        return [
            'id' => (string) $this->id,
            'type' => $this->event ?? $this->description,
            'severity' => $properties['severity'] ?? 'info',
            'module' => $this->log_name ?? ($properties['module'] ?? null),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'result' => $properties['result'] ?? 'success',
            'device_id' => $properties['device_id'] ?? null,
            'ip_address' => $properties['ip_address'] ?? null,
            'user_agent' => $properties['user_agent'] ?? null,
            'context' => $properties['context'] ?? null,
            'created_at' => $this->created_at,
        ];
    }
}
