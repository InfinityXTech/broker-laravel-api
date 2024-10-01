<?php

namespace App\Classes;

use App\Notifications\MailNotification;
use App\Notifications\SlackNotification;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Slack\Messages\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class NotificationReporter
{
    private $channel;
    private string $channelName;
    private string $clientId;
    private static $instance = [];

    public function __construct(string $channel, string $clientId)
    {
        $this->channelName = $channel;
        $this->clientId = $clientId;

        if (empty($clientId)) {
            $config = config('notification');
            $this->channel = $config[$channel] ?? [];
        } else {
            $config = config('clients.' . $clientId . '.notification');
            $this->channel = $config[$channel] ?? [];
        }
    }

    public static function to(string $channel, string $clientId = ''): NotificationReporter
    {
        $key = ($clientId ?? '') . $channel;
        if (isset(self::$instance[$key])) {
            return self::$instance[$key];
        }
        self::$instance[$key] = new self($channel, $clientId);
        return self::$instance[$key];
    }

    public function slack(string $message): void
    {
        Notification::route('slack', '')->notify(new SlackNotification(new SlackMessage($this->channelName, $message, $this->clientId)));
    }

    public function mail(string $message, string $subject): void
    {
        foreach ($this->channel['emails'] ?? [] as $email) {
            $mail = (new MailMessage())->subject($subject)->view('emails.simple', ['title' => 'Hello', 'body' => $message]);
            Notification::route('mail', $email)->notify(new MailNotification($mail));
        }
    }

    public function send(string $message, string $subject)
    {
        $this->slack($message);
        $this->mail($message, $subject);
    }
}
