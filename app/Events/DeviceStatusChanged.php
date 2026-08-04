<?php

namespace App\Events;

use App\Http\Resources\Devices\DeviceResource;
use App\Models\UserDevice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public UserDevice $device,
        public string $action,
    ) {}

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
            new PrivateChannel('user.'.$this->device->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'device.'.$this->action;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'device' => (new DeviceResource($this->device))->resolve(),
        ];
    }
}
