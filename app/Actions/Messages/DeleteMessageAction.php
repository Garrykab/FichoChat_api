<?php

namespace App\Actions\Messages;

use App\Enums\ActivityLogType;
use App\Events\MessageDeleted;
use App\Models\Message;
use App\Models\MessageUserDelete;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class DeleteMessageAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $user, Message $message, string $scope = 'everyone'): Message
    {
        return match ($scope) {
            'me' => $this->deleteForMe($user, $message),
            'everyone' => $this->deleteForEveryone($user, $message),
            default => throw ValidationException::withMessages([
                'scope' => ['Unsupported delete scope. Use me or everyone.'],
            ]),
        };
    }

    private function deleteForMe(User $user, Message $message): Message
    {
        MessageUserDelete::query()->firstOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $user->id,
            ],
            [
                'deleted_at' => now(),
            ],
        );

        $this->auditLogger->activity(
            ActivityLogType::MessageDeletedForMe,
            $user,
            ['conversation_id' => $message->conversation_id],
            Message::class,
            $message->id,
        );

        return $message->load(['receipts', 'sender.profile', 'medias']);
    }

    private function deleteForEveryone(User $user, Message $message): Message
    {
        if ($message->sender_user_id !== $user->id) {
            throw ValidationException::withMessages([
                'message' => ['Only the sender can delete this message for everyone.'],
            ]);
        }

        $message->forceFill([
            'ciphertext' => '',
            'iv' => '',
            'deleted_for_everyone_at' => now(),
            'deleted_by_user_id' => $user->id,
        ])->save();

        $message->delete();

        $deleted = Message::withTrashed()
            ->with(['receipts', 'sender.profile', 'medias'])
            ->findOrFail($message->id);

        MessageDeleted::dispatch($deleted);

        $this->auditLogger->activity(
            ActivityLogType::MessageDeleted,
            $user,
            ['conversation_id' => $message->conversation_id],
            Message::class,
            $message->id,
        );

        return $deleted;
    }
}
