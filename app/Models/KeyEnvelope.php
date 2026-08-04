<?php

namespace App\Models;

use App\Enums\EnvelopeContentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeyEnvelope extends Model
{
    use HasUuids;

    protected $fillable = [
        'content_type',
        'content_id',
        'sender_device_id',
        'recipient_device_id',
        'encrypted_cek',
        'ephemeral_public_key',
        'iv',
        'algorithm',
        'key_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => EnvelopeContentType::class,
            'key_version' => 'integer',
        ];
    }

    public function senderDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'sender_device_id');
    }

    public function recipientDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'recipient_device_id');
    }
}
