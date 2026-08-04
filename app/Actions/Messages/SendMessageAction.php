<?php

namespace App\Actions\Messages;

use App\Actions\Keys\StoreKeyEnvelopesAction;
use App\Enums\EnvelopeContentType;
use App\Enums\MediaStatus;
use App\Enums\MessageType;
use App\Enums\ParticipantStatus;
use App\Enums\ReceiptStatus;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\MessageReceipt;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendMessageAction
{
    public function __construct(
        private readonly StoreKeyEnvelopesAction $storeKeyEnvelopesAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     sender_device_id: string,
     *     ciphertext: string,
     *     iv: string,
     *     type?: string,
     *     reply_to_message_id?: string|null,
     *     media_ids?: list<string>,
     *     envelopes: list<array<string, mixed>>
     * }  $data
     */
    public function execute(User $sender, Conversation $conversation, array $data): Message
    {
        $this->assertParticipant($sender, $conversation);

        $device = UserDevice::query()
            ->where('id', $data['sender_device_id'])
            ->where('user_id', $sender->id)
            ->first();

        if ($device === null || ! $device->isApproved()) {
            throw ValidationException::withMessages([
                'sender_device_id' => ['Sender device must be an approved device of the authenticated user.'],
            ]);
        }

        if (isset($data['reply_to_message_id'])) {
            $reply = Message::query()
                ->where('id', $data['reply_to_message_id'])
                ->where('conversation_id', $conversation->id)
                ->first();

            if ($reply === null) {
                throw ValidationException::withMessages([
                    'reply_to_message_id' => ['Reply target not found in this conversation.'],
                ]);
            }
        }

        $mediaIds = array_values(array_unique($data['media_ids'] ?? []));
        $medias = collect();

        if ($mediaIds !== []) {
            $medias = Media::query()
                ->whereIn('id', $mediaIds)
                ->where('conversation_id', $conversation->id)
                ->where('uploader_user_id', $sender->id)
                ->where('status', MediaStatus::Ready)
                ->whereNull('message_id')
                ->get();

            if ($medias->count() !== count($mediaIds)) {
                throw ValidationException::withMessages([
                    'media_ids' => ['One or more media items are invalid, not ready, or already attached.'],
                ]);
            }
        }

        $participantUserIds = $conversation->participants()->pluck('user_id');

        $message = DB::transaction(function () use ($sender, $conversation, $data, $device, $participantUserIds, $mediaIds, $medias) {
            $type = $data['type'] ?? (
                $medias->isNotEmpty()
                    ? $medias->first()->type->value
                    : MessageType::Text->value
            );

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_user_id' => $sender->id,
                'sender_device_id' => $device->id,
                'type' => $type,
                'ciphertext' => $data['ciphertext'],
                'iv' => $data['iv'],
                'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
            ]);

            if ($mediaIds !== []) {
                Media::query()
                    ->whereIn('id', $mediaIds)
                    ->update(['message_id' => $message->id]);
            }

            $this->storeKeyEnvelopesAction->execute($sender, [
                'content_type' => EnvelopeContentType::Message->value,
                'content_id' => $message->id,
                'sender_device_id' => $device->id,
                'envelopes' => $data['envelopes'],
            ]);

            foreach ($participantUserIds as $userId) {
                if ($userId === $sender->id) {
                    continue;
                }

                MessageReceipt::query()->create([
                    'message_id' => $message->id,
                    'user_id' => $userId,
                    'status' => ReceiptStatus::Sent,
                ]);
            }

            $conversation->forceFill(['last_message_at' => now()])->save();

            // Réactive les participants archivés/masqués côté destinataires à la réception d’un nouveau message
            $conversation->participants()
                ->where('user_id', '!=', $sender->id)
                ->whereIn('status', [ParticipantStatus::Archived, ParticipantStatus::Hidden])
                ->update([
                    'status' => ParticipantStatus::Active,
                    'archived_at' => null,
                    'hidden_at' => null,
                ]);

            return $message->load(['receipts', 'sender.profile', 'medias']);
        });

        \App\Events\MessageSent::dispatch($message);

        $this->auditLogger->activity(
            \App\Enums\ActivityLogType::MessageSent,
            $sender,
            [
                'conversation_id' => $conversation->id,
                'message_type' => $message->type?->value,
            ],
            Message::class,
            $message->id,
            deviceId: $data['sender_device_id'],
        );

        return $message;
    }

    private function assertParticipant(User $user, Conversation $conversation): void
    {
        $exists = $conversation->participants()
            ->where('user_id', $user->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'conversation' => ['You are not a participant of this conversation.'],
            ]);
        }
    }
}
