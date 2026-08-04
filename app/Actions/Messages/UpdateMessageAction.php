<?php

namespace App\Actions\Messages;

use App\Enums\ActivityLogType;
use App\Events\MessageUpdated;
use App\Models\Message;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class UpdateMessageAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{ciphertext: string, iv: string}  $data
     */
    public function execute(User $user, Message $message, array $data): Message
    {
        if ($message->sender_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'message' => ['Only the sender can edit this message.'],
            ]);
        }

        if ($message->isDeletedForEveryone() || $message->trashed()) {
            throw ValidationException::withMessages([
                'message' => ['Deleted messages cannot be edited.'],
            ]);
        }

        $message->forceFill([
            'ciphertext' => $data['ciphertext'],
            'iv' => $data['iv'],
            'edited_at' => now(),
        ])->save();

        $fresh = $message->fresh()->load(['receipts', 'sender.profile', 'medias']);
        MessageUpdated::dispatch($fresh);

        $this->auditLogger->activity(
            ActivityLogType::MessageUpdated,
            $user,
            ['conversation_id' => $message->conversation_id],
            Message::class,
            $message->id,
        );

        return $fresh;
    }
}
