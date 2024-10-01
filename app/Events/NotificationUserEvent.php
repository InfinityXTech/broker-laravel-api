<?php

namespace App\Events;

use App\Helpers\GeneralHelper;
use App\Models\NotificationMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
// use Cmgmyr\Messenger\Models\Message;

class NotificationUserEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public NotificationMessage $message;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(NotificationMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastWith()
    {
        return ["message" => $this->message];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // GeneralHelper::PrintR(['notifications.user.' . Auth::id()]);die();
        // return new PrivateChannel('notifications.user.' . $this->message->userId);
        return [(config('app.env') == 'production' ? 'production.' : '') . 'notifications.user.' . $this->message->userId];
    }

    // public function broadcastOn()
    // {
    //     return new PrivateChannel('backoffice-activity');
    // }

    public function broadcastAs()
    {
        // return new PrivateChannel('message');
        return 'message';
    }
}
