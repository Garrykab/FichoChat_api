<?php

namespace App\Actions\Sync;

use App\Enums\EnvelopeContentType;
use App\Enums\MediaStatus;
use App\Models\Conversation;
use App\Models\KeyEnvelope;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Validation\ValidationException;

class ListMissingEnvelopesAction
{
    private const MAX_ITEMS = 500;

    /**
     * Contenu historique pour lequel le nouvel appareil n’a pas encore d’enveloppe CEK.
     *
     * @return array{messages: list<array<string, mixed>>, medias: list<array<string, mixed>>}
     */
    public function execute(User $user, UserDevice $actor, UserDevice $target): array
    {
        if ($actor->user_id !== $user->id || ! $actor->isApproved()) {
            throw ValidationException::withMessages([
                'actor_device_id' => ['Actor device must be an approved device of the authenticated user.'],
            ]);
        }

        if ($target->user_id !== $user->id || ! $target->isApproved()) {
            throw ValidationException::withMessages([
                'target_device_id' => ['Target device must be an approved device of the authenticated user.'],
            ]);
        }

        if ($actor->id === $target->id) {
            throw ValidationException::withMessages([
                'target_device_id' => ['Target device must be different from the actor device.'],
            ]);
        }

        $conversationIds = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $messageIdsWithEnvelopeForTarget = KeyEnvelope::query()
            ->where('content_type', EnvelopeContentType::Message)
            ->where('recipient_device_id', $target->id)
            ->pluck('content_id');

        $messages = Message::query()
            ->whereIn('conversation_id', $conversationIds)
            ->whereNotIn('id', $messageIdsWithEnvelopeForTarget)
            ->whereNull('deleted_for_everyone_at')
            ->orderByDesc('created_at')
            ->limit(self::MAX_ITEMS)
            ->get(['id', 'conversation_id', 'sender_device_id', 'created_at']);

        // Prefer items the actor can decrypt (has own envelope)
        $actorMessageEnvelopeIds = KeyEnvelope::query()
            ->where('content_type', EnvelopeContentType::Message)
            ->where('recipient_device_id', $actor->id)
            ->whereIn('content_id', $messages->pluck('id'))
            ->pluck('content_id');

        $shareableMessages = $messages
            ->filter(fn (Message $message) => $actorMessageEnvelopeIds->contains($message->id))
            ->values()
            ->map(fn (Message $message) => [
                'content_type' => EnvelopeContentType::Message->value,
                'content_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_device_id' => $message->sender_device_id,
                'created_at' => $message->created_at?->toISOString(),
            ])
            ->all();

        $mediaIdsWithEnvelopeForTarget = KeyEnvelope::query()
            ->where('content_type', EnvelopeContentType::Media)
            ->where('recipient_device_id', $target->id)
            ->pluck('content_id');

        $medias = Media::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('status', MediaStatus::Ready)
            ->whereNotNull('message_id')
            ->whereNotIn('id', $mediaIdsWithEnvelopeForTarget)
            ->orderByDesc('created_at')
            ->limit(self::MAX_ITEMS)
            ->get(['id', 'conversation_id', 'message_id', 'uploader_device_id', 'type', 'created_at']);

        $actorMediaEnvelopeIds = KeyEnvelope::query()
            ->where('content_type', EnvelopeContentType::Media)
            ->where('recipient_device_id', $actor->id)
            ->whereIn('content_id', $medias->pluck('id'))
            ->pluck('content_id');

        $shareableMedias = $medias
            ->filter(fn (Media $media) => $actorMediaEnvelopeIds->contains($media->id))
            ->values()
            ->map(fn (Media $media) => [
                'content_type' => EnvelopeContentType::Media->value,
                'content_id' => $media->id,
                'conversation_id' => $media->conversation_id,
                'message_id' => $media->message_id,
                'uploader_device_id' => $media->uploader_device_id,
                'type' => $media->type?->value,
                'created_at' => $media->created_at?->toISOString(),
            ])
            ->all();

        return [
            'target_device_id' => $target->id,
            'target_public_key' => $target->public_key,
            'messages' => $shareableMessages,
            'medias' => $shareableMedias,
        ];
    }
}
