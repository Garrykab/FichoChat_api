<?php

namespace App\Actions\Conversations;

use App\Enums\ActivityLogType;
use App\Enums\ParticipantStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class UpdateParticipantStatusAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function archive(User $user, Conversation $conversation): ConversationParticipant
    {
        return $this->apply($user, $conversation, ParticipantStatus::Archived);
    }

    public function unarchive(User $user, Conversation $conversation): ConversationParticipant
    {
        return $this->apply($user, $conversation, ParticipantStatus::Active);
    }

    public function hide(User $user, Conversation $conversation): ConversationParticipant
    {
        return $this->apply($user, $conversation, ParticipantStatus::Hidden);
    }

    private function apply(
        User $user,
        Conversation $conversation,
        ParticipantStatus $status,
    ): ConversationParticipant {
        $participant = $conversation->participants()
            ->where('user_id', $user->id)
            ->first();

        if ($participant === null) {
            throw ValidationException::withMessages([
                'conversation' => ['You are not a participant of this conversation.'],
            ]);
        }

        $participant->forceFill(match ($status) {
            ParticipantStatus::Active => [
                'status' => $status,
                'archived_at' => null,
                'hidden_at' => null,
            ],
            ParticipantStatus::Archived => [
                'status' => $status,
                'archived_at' => now(),
                'hidden_at' => null,
            ],
            ParticipantStatus::Hidden => [
                'status' => $status,
                'hidden_at' => now(),
            ],
        })->save();

        if ($status === ParticipantStatus::Archived) {
            $this->auditLogger->activity(
                ActivityLogType::ConversationArchived,
                $user,
                [],
                Conversation::class,
                $conversation->id,
            );
        }

        if ($status === ParticipantStatus::Hidden) {
            $this->auditLogger->activity(
                ActivityLogType::ConversationHidden,
                $user,
                [],
                Conversation::class,
                $conversation->id,
            );
        }

        return $participant->fresh();
    }
}
