<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Broadcast::routes();

        // Broadcast::routes(["middleware" => ["auth:api"]]);

        Broadcast::routes([
            'prefix' => 'api',
            'middleware' => 'auth:api',
            // 'middleware'=>['auth:central_admin_api,api,customer_api,deliver_api']
        ]);

        require base_path('routes/channels.php');
    }
}
