<?php

namespace App\Helpers;

use App\Models\Client;
use App\Helpers\BucketHelper;
use PharIo\Manifest\Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class ClientHelper
{
    public static function clients(bool $cache = true): array
    {
        return BucketHelper::get_clients($cache);
        // $clients = false; //Cache::get('clients');
        // if (!$clients) {
        //     $clients = Client::query()->whereIn('status', ['1', 1, true])->get()->toArray();
        //     Cache::put('clients', $clients, 60 * 60 * 24); // 24 hours
        // }
        // return $clients;
    }

    public static function clientConfig(): array
    {
        $clientId = self::clientId();
        foreach (self::clients() as $client) {
            if ($clientId == (string)$client['_id']) {
                return $client;
            }
        }
        return [];
    }

    public static function clientId(bool $cache = true): string
    {
        // $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']), 'https') === FALSE ? 'http' : 'https';
        // $domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
        $domain = trim(strtolower($_SERVER['HTTP_HOST'] ?? ''));
        $domain = str_replace('http://', '', str_replace('https://', '', $domain));

        if (
            // strpos($domain, 'saas_dev-api.albertroi.com') !== false ||
            strpos($domain, '127.0.0.1') !== false ||
            strpos($domain, 'localhost') !== false ||
            !app()->isProduction()
        ) {
            // return '633562373712d253c05e7581';
            return '649aaee724d4153b1e0a88ee';
            // $domain = 'qa-api-crypt.markertech.club';
        }

        if (!empty($domain)) {
            $cache_key = 'clientId[' . $domain . ']';
            $clientId = false;
            if ($cache) {
                $clientId = Cache::get($cache_key);
            }

            if ($clientId) {
                return $clientId;
            } else {
                $clients = self::clients($cache);

                foreach ($clients as $client) {
                    $status = ((int)($client['status'] ?? 0));
                    if ($status == 1) {
                        $url = trim(strtolower($client['api_domain']));
                        $url = parse_url($url, PHP_URL_HOST);

                        if ($url == $domain) {
                            if ($cache) {
                                Cache::put($cache_key, (string)$client['_id'], 60); // 1 minute
                            }
                            return (string)$client['_id'];
                        }
                    }
                }
            }
        }

        return '';
    }

    public static function setEmailConfig(string $clientId = ''): array
    {
        if (empty($clientId)) {
            $clientId = self::clientId();
        }
        $email_config = config('clients.' . $clientId . '.mail') ?? [];
        if (!empty($email_config)) {
            $smtp = $email_config['mailers'][$email_config['default']];
            Config::set('mail.mailers.smtp_' . $clientId, $smtp);
            $email_config['mailer_name'] = 'smtp_' . $clientId;
        } else {
            $email_config = config('mail') ?? [];
            $email_config['mailer_name'] = 'mail';
        }

        return $email_config;
    }

    public static function get_bucket_client(string $clientId = ''): array
    {
        return BucketHelper::get_client($clientId);
    }

    /**
     * is_public_features
     *
     * @param mixed $features
     * @return boolean
     */
    public static function is_public_features($features): bool
    {
        return BucketHelper::is_public_features($features);
    }

    /**
     * is_private_features
     *
     * @param mixed $features
     * @return boolean
     */
    public static function is_private_features($features): bool
    {
        return BucketHelper::is_private_features($features);
    }
}
