<?php

use App\Helpers\ClientHelper;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

// $app->loadEnvironmentFrom('.env');

$environment = $_SERVER['HTTP_ENVIRONMENT'] ?? '';
$clientId = '';
try {
    // $clientId = ClientHelper::clientId(false);
    // if (empty($clientId)) {
    //     $clientId = 'unknown';
    // }

    $file_clients_path = dirname(__DIR__) . '/storage/app/clients.json';
    if (file_exists($file_clients_path)) {
        $json = file_get_contents($file_clients_path);
        if (!empty($json)) {
            $domain = trim(strtolower($_SERVER['HTTP_HOST'] ?? ''));
            $domain = str_replace('http://', '', str_replace('https://', '', $domain));

            if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false || !app()->isProduction()) {
                $clientId = '649aaee724d4153b1e0a88ee';
                // $clientId = '633562373712d253c05e7581'; //qa-api.markertech.club
                // $domain = 'qa-api.markertech.club';
                // $domain = 'qa-api-crypt.markertech.club';
            }

            if (empty($clientId)) {
                $clients = json_decode($json, true) ?? [];
                foreach ($clients as $client) {
                    $status = ((int)($client['status'] ?? 0));
                    if ($status == 1) {
                        $url = trim(strtolower($client['api_domain']));
                        $url = parse_url($url, PHP_URL_HOST);
                        if ($url == $domain) {
                            $clientId = (string)$client['_id'];
                            break;
                        }
                    }
                }
            }
        }
    }
} catch (Exception $ex) {
}

// if (empty($clientId)) {
//     header('HTTP/1.1 403 Forbidden');
//     die('Forbidden');
// }

$client_env = '.env.' . $clientId . '.php';
$basedir = ($_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)) . '/';
if (!empty($environment) && file_exists($basedir . '.env.' . $environment . '.php')) {
    $app->loadEnvironmentFrom('.env.' . $environment . '.php');
} else if (file_exists($basedir . $client_env)) {
    // $app->loadEnvironmentFrom(basename($client_env, '.php'));
    $app->loadEnvironmentFrom($client_env);
} else {
    $app->loadEnvironmentFrom('.env'); // Or just do nothing
}

// This is your custom
// $yourCondition = 'local';
// switch ($yourCondition) {
//     case 'local':
//         $app->loadEnvironmentFrom('.local.env');
//         break;
//     default:
//         $app->loadEnvironmentFrom('.env'); // Or just do nothing
//         break;
// };

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

// $app->register(Jenssegers\Mongodb\MongodbServiceProvider::class);
// $app->withEloquent();

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
