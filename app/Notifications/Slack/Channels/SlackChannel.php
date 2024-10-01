<?php

namespace App\Notifications\Slack\Channels;

use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Classes\SlackNotification;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Notifications\Notification;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Notifications\Slack\Messages\SlackAttachment;
use App\Notifications\Slack\Messages\SlackAttachmentField;

class SlackChannel
{
    /**
     * The HTTP client instance.
     *
     * @var \GuzzleHttp\Client
     */
    protected $http;

    /**
     * Create a new Slack channel instance.
     *
     * @param  \GuzzleHttp\Client  $http
     * @return void
     */
    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function send($notifiable, Notification $notification)
    {
        // if (!$url = $notifiable->routeNotificationFor('slack', $notification)) {
        //     return;
        // }

        // return $this->http->post($url, $this->buildJsonPayload($message));

        $message = $notification->toSlack($notifiable);

        if (!empty($message->clientId)) {
            $clientId = $message->clientId;
        } else {
            $clientId = ClientHelper::clientId();
        }

        $api_key = config('clients.' . $clientId . '.slack.api_key');
        if (empty($api_key)) {
            $api_key = config('slack.api_key');
        }

        $notification_config = config('clients.' . $clientId . '.notification');
        if (empty($notification_config)) {
            $notification_config = config('notification');
        }

        $channel_config = $notification_config[$message->channel] ?? [];

        $slack = SlackNotification::instance($api_key);
        foreach ($channel_config['slack_channels'] ?? [] as $channel) {
            if (isset($channel['id'])) {
                $slack->send_to_channel_id($message->content, $channel['id']);
            } else {
                $slack->send_to_channel_name($message->content, $channel['name']);
            }
        }
    }

    /**
     * Build up a JSON payload for the Slack webhook.
     *
     * @param  \App\Notifications\Slack\Messages\SlackMessage  $message
     * @return array
     */
    protected function buildJsonPayload(SlackMessage $message)
    {
        $optionalFields = array_filter([
            'channel' => data_get($message, 'channel'),
            'icon_emoji' => data_get($message, 'icon'),
            'icon_url' => data_get($message, 'image'),
            'link_names' => data_get($message, 'linkNames'),
            'unfurl_links' => data_get($message, 'unfurlLinks'),
            'unfurl_media' => data_get($message, 'unfurlMedia'),
            'username' => data_get($message, 'username'),
        ]);

        return array_merge([
            'json' => array_merge([
                'text' => $message->content,
                'attachments' => $this->attachments($message),
            ], $optionalFields),
        ], $message->http);
    }

    /**
     * Format the message's attachments.
     *
     * @param  \App\Notifications\Slack\Messages\SlackMessage  $message
     * @return array
     */
    protected function attachments(SlackMessage $message)
    {
        return collect($message->attachments)->map(function ($attachment) use ($message) {
            return array_filter([
                'actions' => $attachment->actions,
                'author_icon' => $attachment->authorIcon,
                'author_link' => $attachment->authorLink,
                'author_name' => $attachment->authorName,
                'callback_id' => $attachment->callbackId,
                'color' => $attachment->color ?: $message->color(),
                'fallback' => $attachment->fallback,
                'fields' => $this->fields($attachment),
                'footer' => $attachment->footer,
                'footer_icon' => $attachment->footerIcon,
                'image_url' => $attachment->imageUrl,
                'mrkdwn_in' => $attachment->markdown,
                'pretext' => $attachment->pretext,
                'text' => $attachment->content,
                'thumb_url' => $attachment->thumbUrl,
                'title' => $attachment->title,
                'title_link' => $attachment->url,
                'ts' => $attachment->timestamp,
            ]);
        })->all();
    }

    /**
     * Format the attachment's fields.
     *
     * @param  \Illuminate\Notifications\Messages\SlackAttachment  $attachment
     * @return array
     */
    protected function fields(SlackAttachment $attachment)
    {
        return collect($attachment->fields)->map(function ($value, $key) {
            if ($value instanceof SlackAttachmentField) {
                return $value->toArray();
            }

            return ['title' => $key, 'value' => $value, 'short' => true];
        })->values()->all();
    }
}
