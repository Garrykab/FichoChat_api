<?php

namespace App\Http\Resources\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserSetting
 */
class UserSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'notifications_enabled' => $this->notifications_enabled,
            'notify_messages' => $this->notify_messages,
            'notify_devices' => $this->notify_devices,
            'notify_security' => $this->notify_security,
            'hide_message_previews' => $this->hide_message_previews,
            'silent_mode' => $this->silent_mode,
            'send_read_receipts' => $this->send_read_receipts,
            'show_last_seen' => $this->show_last_seen,
            'auto_download_media' => $this->auto_download_media,
            'updated_at' => $this->updated_at,
        ];
    }
}
