<?php

namespace App\Providers;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Slack\Channels\SlackChannel;

class SlackChannelServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('slack', function ($app) {
                return new SlackChannel($app->make(HttpClient::class));
            });
        });
    }
}
