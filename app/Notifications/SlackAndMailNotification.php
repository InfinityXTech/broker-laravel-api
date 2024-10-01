<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Slack\Messages\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class SlackAndMailNotification extends Notification
{
    use Queueable;

    private SlackMessage $slackMessage;
    private SlackMessage $mailMessage;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(SlackMessage $slackMessage, MailMessage $mailMessage)
    {
        $this->slackMessage = $slackMessage;
        $this->mailMessage = $mailMessage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \App\Notifications\Messages\MailMessage
     */
    public function toSlack($notifiable)
    {
        return $this->slackMessage;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \App\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return $this->mailMessage;
        // (new MailMessage)
        //     ->greeting($this->project['greeting'])
        //     ->line($this->project['body'])
        //     ->action($this->project['actionText'], $this->project['actionURL'])
        //     ->line($this->project['thanks']);
    }
}
