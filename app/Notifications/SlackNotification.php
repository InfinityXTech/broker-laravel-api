<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Slack\Messages\SlackMessage;

class SlackNotification extends Notification
{
    use Queueable;

    private SlackMessage $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SlackMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \App\Notifications\Messages\MailMessage
     */
    public function toSlack($notifiable)
    {
        return ($this->message);
    }
}
