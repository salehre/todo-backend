<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MessageReacted implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public int $groupId, public array $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel("group.{$this->groupId}");
    }

    public function broadcastAs(): string
    {
        return 'message.reacted';
    }
}
