<?php

namespace App\Models;

use App\Enums\ParticipantRole;
use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'status',
        'archived_at',
        'hidden_at',
        'last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'status' => ParticipantStatus::class,
            'archived_at' => 'datetime',
            'hidden_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === ParticipantStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === ParticipantStatus::Archived;
    }
}
