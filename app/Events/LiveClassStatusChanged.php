<?php

namespace App\Events;

use App\Models\LiveClass;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveClassStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $id;
    public $status;
    public $title;
    public $school_id;

    public function __construct(LiveClass $liveClass)
    {
        $this->id = $liveClass->id;
        $this->status = $liveClass->status;
        $this->title = $liveClass->title;
        $this->school_id = $liveClass->school_id;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('school.' . $this->school_id . '.live-classes'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'LiveClassStatusChanged';
    }
}