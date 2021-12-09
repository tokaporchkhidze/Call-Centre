<?php

namespace App\Events;

use App\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ExcelCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $absoluteFilePath;

    /**
     * @var User
     */
    public $user;

    public $connection = 'notificationQueue';

    public $queue = 'notificationJob';

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($absoluteFilePath, $user)
    {
        $this->absoluteFilePath = $absoluteFilePath;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('channel-name');
    }
}
