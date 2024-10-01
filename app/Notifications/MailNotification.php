<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class MailNotification extends Notification
{
    use Queueable;

    private MailMessage $mailMessage;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(MailMessage $mailMessage)
    {
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
        return ['mail'];
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
