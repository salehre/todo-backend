<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class InviteSent implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public int $userId, public array $invite) {}

    public function broadcastOn(): Channel
    {
        return new \Illuminate\Broadcasting\PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'invite.sent';
    }
}
