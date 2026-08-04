<?php

namespace App\Events;

use App\Http\Resources\Messages\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusion asynchrone via la queue (pas dans la requête HTTP métier).
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['receipts', 'sender.profile', 'medias', 'conversation.participants']);
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];

        $participantIds = $this->message->conversation?->participants
            ?->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        foreach ($participantIds as $userId) {
            $channels[] = new PrivateChannel('user.'.$userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
