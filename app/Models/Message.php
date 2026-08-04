<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'sender_device_id',
        'type',
        'ciphertext',
        'iv',
        'reply_to_message_id',
        'edited_at',
        'deleted_for_everyone_at',
        'deleted_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
            'edited_at' => 'datetime',
            'deleted_for_everyone_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function senderDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'sender_device_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(MessageReceipt::class);
    }

    public function medias(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function userDeletes(): HasMany
    {
        return $this->hasMany(MessageUserDelete::class);
    }

    public function isDeletedForEveryone(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }

    public function isDeletedForUser(string $userId): bool
    {
        return $this->userDeletes()
            ->where('user_id', $userId)
            ->exists();
    }
}
