<?php

namespace App\Events;

use App\Http\Resources\Messages\MessageReceiptResource;
use App\Models\MessageReceipt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReceiptUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public MessageReceipt $receipt)
    {
        $this->receipt->loadMissing('message');
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
        return [
            new PrivateChannel('conversation.'.$this->receipt->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.receipt';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'receipt' => (new MessageReceiptResource($this->receipt))->resolve(),
            'message_id' => $this->receipt->message_id,
            'conversation_id' => $this->receipt->message->conversation_id,
        ];
    }
}
