<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasUuids;

    protected $table = 'user_settings';

    protected $fillable = [
        'user_id',
        'notifications_enabled',
        'notify_messages',
        'notify_devices',
        'notify_security',
        'hide_message_previews',
        'silent_mode',
            'send_read_receipts',
            'show_last_seen',
            'auto_download_media',
        ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'notify_messages' => 'boolean',
            'notify_devices' => 'boolean',
            'notify_security' => 'boolean',
            'hide_message_previews' => 'boolean',
            'silent_mode' => 'boolean',
            'send_read_receipts' => 'boolean',
            'show_last_seen' => 'boolean',
            'auto_download_media' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
