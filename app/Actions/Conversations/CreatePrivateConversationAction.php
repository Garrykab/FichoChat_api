<?php

namespace App\Actions\Conversations;

use App\Enums\ActivityLogType;
use App\Enums\ConversationType;
use App\Enums\ParticipantRole;
use App\Enums\ParticipantStatus;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePrivateConversationAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{user_id: string}  $data
     */
    public function execute(User $creator, array $data): Conversation
    {
        if ($data['user_id'] === $creator->id) {
            throw ValidationException::withMessages([
                'user_id' => ['You cannot start a conversation with yourself.'],
            ]);
        }

        $peer = User::query()->find($data['user_id']);

        if ($peer === null || $peer->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not available.'],
            ]);
        }

        $pairKey = Conversation::pairKeyFor($creator->id, $peer->id);

        $existing = Conversation::withTrashed()
            ->where('type', ConversationType::Private)
            ->where('pair_key', $pairKey)
            ->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            // Réactive la participation du créateur si masquée / archivée
            $existing->participants()
                ->where('user_id', $creator->id)
                ->update([
                    'status' => ParticipantStatus::Active,
                    'archived_at' => null,
                    'hidden_at' => null,
                ]);

            return $existing->load(['participants.user.profile', 'participants.user.settings']);
        }

        $conversation = DB::transaction(function () use ($creator, $peer, $pairKey) {
            $conversation = Conversation::query()->create([
                'type' => ConversationType::Private,
                'created_by_user_id' => $creator->id,
                'pair_key' => $pairKey,
            ]);

            $conversation->participants()->create([
                'user_id' => $creator->id,
                'role' => ParticipantRole::Owner,
                'status' => ParticipantStatus::Active,
            ]);

            $conversation->participants()->create([
                'user_id' => $peer->id,
                'role' => ParticipantRole::Member,
                'status' => ParticipantStatus::Active,
            ]);

            return $conversation->load(['participants.user.profile', 'participants.user.settings']);
        });

        $this->auditLogger->activity(
            ActivityLogType::ConversationCreated,
            $creator,
            ['peer_user_id' => $peer->id],
            Conversation::class,
            $conversation->id,
        );

        return $conversation;
    }
}
