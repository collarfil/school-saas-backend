<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamSignal implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $userIdToCall, 
        public $signalData,
        public $fromId
    ) {}

    public function broadcastOn(): array
    {
        // Broadcast on a private channel unique to the recipient
        return [
            new PrivateChannel('user.' . $this->userIdToCall),
        ];
    }
}