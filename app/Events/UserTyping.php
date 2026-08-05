<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public int $groupId, public int $userId, public string $userName) {}

    public function broadcastOn(): Channel
    {
        return new Channel("group.{$this->groupId}");
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }
}
