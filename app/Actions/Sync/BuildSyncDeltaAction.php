<?php

namespace App\Actions\Sync;

use App\Enums\EnvelopeContentType;
use App\Enums\ParticipantStatus;
use App\Http\Resources\Conversations\ConversationResource;
use App\Http\Resources\Keys\KeyEnvelopeResource;
use App\Http\Resources\Media\MediaResource;
use App\Http\Resources\Messages\MessageResource;
use App\Models\Conversation;
use App\Models\KeyEnvelope;
use App\Models\Media;
use App\Models\Message;
use App\Models\MessageReceipt;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BuildSyncDeltaAction
{
    public function __construct(
        private readonly EnsureDeviceSyncStateAction $ensureDeviceSyncStateAction,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $user, UserDevice $device, CarbonImmutable $since): array
    {
        if ($device->user_id !== $user->id || ! $device->isApproved()) {
            throw ValidationException::withMessages([
                'device_id' => ['Device must be an approved device of the authenticated user.'],
            ]);
        }

        $state = $this->ensureDeviceSyncStateAction->execute($device);

        $conversationIds = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->where(function ($q) use ($user, $since): void {
                $q->where('updated_at', '>', $since)
                    ->orWhere('last_message_at', '>', $since)
                    ->orWhereHas('participants', function ($pq) use ($user, $since): void {
                        $pq->where('user_id', $user->id)->where('updated_at', '>', $since);
                    });
            })
            ->with(['participants.user.profile'])
            ->limit(100)
            ->get();

        $messages = Message::withTrashed()
            ->whereIn('conversation_id', $conversationIds)
            ->where(function ($q) use ($since): void {
                $q->where('created_at', '>', $since)
                    ->orWhere('updated_at', '>', $since)
                    ->orWhere('deleted_at', '>', $since);
            })
            ->whereDoesntHave('userDeletes', fn ($q) => $q->where('user_id', $user->id))
            ->with(['receipts', 'sender.profile', 'medias'])
            ->orderBy('created_at')
            ->limit(500)
            ->get();

        $messageIds = $messages->pluck('id');

        $medias = Media::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where(function ($q) use ($messageIds, $since): void {
                $q->whereIn('message_id', $messageIds)
                    ->orWhere('updated_at', '>', $since);
            })
            ->limit(500)
            ->get();

        $receipts = MessageReceipt::query()
            ->where(function ($q) use ($messageIds, $user, $since): void {
                $q->whereIn('message_id', $messageIds)
                    ->orWhere(function ($rq) use ($user, $since): void {
                        $rq->where('user_id', $user->id)->where('updated_at', '>', $since);
                    });
            })
            ->limit(500)
            ->get()
            ->map(fn (MessageReceipt $receipt) => [
                'id' => $receipt->id,
                'message_id' => $receipt->message_id,
                'user_id' => $receipt->user_id,
                'device_id' => $receipt->device_id,
                'status' => $receipt->status?->value,
                'delivered_at' => $receipt->delivered_at?->toISOString(),
                'read_at' => $receipt->read_at?->toISOString(),
                'updated_at' => $receipt->updated_at?->toISOString(),
            ]);

        $envelopes = KeyEnvelope::query()
            ->where('recipient_device_id', $device->id)
            ->where('created_at', '>', $since)
            ->whereIn('content_type', [
                EnvelopeContentType::Message->value,
                EnvelopeContentType::Media->value,
                EnvelopeContentType::Sync->value,
            ])
            ->orderBy('created_at')
            ->limit(1000)
            ->get();

        $hiddenConversationIds = Conversation::query()
            ->whereHas('participants', function ($q) use ($user, $since): void {
                $q->where('user_id', $user->id)
                    ->where('status', ParticipantStatus::Hidden)
                    ->where('updated_at', '>', $since);
            })
            ->pluck('id');

        return [
            'state' => [
                'device_id' => $state->device_id,
                'status' => $state->status?->value,
                'cursor_at' => $state->cursor_at?->toISOString(),
                'bootstrap_completed_at' => $state->bootstrap_completed_at?->toISOString(),
                'needs_bootstrap' => $state->needsBootstrap(),
            ],
            'since' => $since->toISOString(),
            'conversations' => ConversationResource::collection($conversations),
            'messages' => MessageResource::collection($messages),
            'medias' => MediaResource::collection($medias),
            'receipts' => $receipts,
            'envelopes' => KeyEnvelopeResource::collection($envelopes),
            'hidden_conversation_ids' => $hiddenConversationIds,
            'generated_at' => now()->toISOString(),
        ];
    }
}
