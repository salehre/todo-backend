<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class MessagePinned implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public int $groupId,
        public int $messageId,
        public bool $pinned
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("group.{$this->groupId}");
    }

    public function broadcastAs(): string
    {
        return 'message.pinned';
    }
}
