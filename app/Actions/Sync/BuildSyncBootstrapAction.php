<?php

namespace App\Actions\Sync;

use App\Enums\ActivityLogType;
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
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class BuildSyncBootstrapAction
{
    private const MAX_CONVERSATIONS = 50;

    private const MAX_MESSAGES_PER_CONVERSATION = 100;

    public function __construct(
        private readonly EnsureDeviceSyncStateAction $ensureDeviceSyncStateAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $user, UserDevice $device): array
    {
        $this->assertOwnedApprovedDevice($user, $device);

        $state = $this->ensureDeviceSyncStateAction->execute($device);

        $conversations = Conversation::query()
            ->whereHas('participants', function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->whereIn('status', [
                        ParticipantStatus::Active,
                        ParticipantStatus::Archived,
                        ParticipantStatus::Hidden,
                    ]);
            })
            ->with(['participants.user.profile'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(self::MAX_CONVERSATIONS)
            ->get();

        $messagesByConversation = [];
        $allMessageIds = [];

        foreach ($conversations as $conversation) {
            $messages = Message::withTrashed()
                ->where('conversation_id', $conversation->id)
                ->whereDoesntHave('userDeletes', fn ($q) => $q->where('user_id', $user->id))
                ->with(['receipts', 'sender.profile', 'medias'])
                ->orderByDesc('created_at')
                ->limit(self::MAX_MESSAGES_PER_CONVERSATION)
                ->get()
                ->sortBy('created_at')
                ->values();

            $messagesByConversation[$conversation->id] = MessageResource::collection($messages);
            $allMessageIds = array_merge($allMessageIds, $messages->pluck('id')->all());
        }

        $medias = Media::query()
            ->whereIn('message_id', $allMessageIds)
            ->where('status', 'ready')
            ->get();

        $envelopes = KeyEnvelope::query()
            ->where('recipient_device_id', $device->id)
            ->whereIn('content_type', [
                EnvelopeContentType::Message->value,
                EnvelopeContentType::Media->value,
                EnvelopeContentType::Sync->value,
            ])
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get();

        $receipts = MessageReceipt::query()
            ->whereIn('message_id', $allMessageIds)
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

        $payload = [
            'state' => [
                'device_id' => $state->device_id,
                'status' => $state->status?->value,
                'cursor_at' => $state->cursor_at?->toISOString(),
                'bootstrap_completed_at' => $state->bootstrap_completed_at?->toISOString(),
                'needs_bootstrap' => $state->needsBootstrap(),
            ],
            'conversations' => ConversationResource::collection($conversations),
            'messages_by_conversation' => $messagesByConversation,
            'medias' => MediaResource::collection($medias),
            'envelopes' => KeyEnvelopeResource::collection($envelopes),
            'receipts' => $receipts,
            'generated_at' => now()->toISOString(),
        ];

        $this->auditLogger->activity(
            ActivityLogType::SyncBootstrap,
            $user,
            [
                'conversations_count' => $conversations->count(),
                'medias_count' => $medias->count(),
            ],
            deviceId: $device->id,
        );

        return $payload;
    }

    private function assertOwnedApprovedDevice(User $user, UserDevice $device): void
    {
        if ($device->user_id !== $user->id || ! $device->isApproved()) {
            throw ValidationException::withMessages([
                'device_id' => ['Device must be an approved device of the authenticated user.'],
            ]);
        }
    }
}
