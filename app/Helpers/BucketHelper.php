<?php


namespace App\Helpers;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BucketHelper
{
    private static $storage = null;

    public static function getInstance()
    {
        try {
            if (self::$storage) {
                return self::$storage;
            }

            $config = [];
            $key_file_path = '';
            try {
                $gcs = config('filesystems.disks.gcs', []) ?? [];
                $key_file_path = $gcs['key_file'] ?? '';
            } catch (Exception $ex) {
            }
            if (!empty($key_file_path)) {
                if (!file_exists($key_file_path)) {
                    $key_file_path = storage_path('app') . '/' . $key_file_path;
                }
                if (file_exists($key_file_path)) {
                    // $config = json_decode(file_get_contents($key_file_path), true);
                    $config['keyFilePath'] = $key_file_path;
                }
            }

            self::$storage = new \Google\Cloud\Storage\StorageClient($config);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
        return self::$storage;
    }

    public static function get(string $path, string $bucketName, $default = null)
    {
        try {
            $storage = self::getInstance();
            if (isset($storage)) {
                $prefix = config('gsbucket.prefix', '') ?? '';
                $bucketName = (!empty($prefix) ? $prefix . '_' : '') . $bucketName;

                $local_path = config('gsbucket.local_path', '') ?? '';
                if (!empty($local_path)) {
                    $file_path = str_replace('//', '/', $local_path . '/' . $bucketName  . '/' . $path);
                    if (file_exists($file_path)) {
                        return file_get_contents($file_path);
                    }
                }

                $bucket = $storage->bucket($bucketName);
                $object = $bucket->object($path);

                if ($object->exists()) {
                    $jsonData = $object->downloadAsString();
                    return $jsonData;
                }
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }

        return $default;
    }

    public static function get_clients(bool $cache = true): array
    {
        $cache_key = "bucket/clients/clients.json";
        $clients = false;
        if ($cache) {
            $clients = Cache::get($cache_key);
        }
        if ($clients && is_array($clients)) {
            return $clients;
        }

        try {
            $path = "clients.json";
            $backet_name = "serving_logs";
            $json = BucketHelper::get($path, $backet_name);
            $clients = null;
            if (is_string($json) && !empty($json)) {
                $clients = json_decode($json, true);
            }
            if (isset($clients) && is_array($clients)) {
                $clients = array_merge(array_filter($clients, fn ($cl) => in_array($cl['client_type'] ?? '', ['saas', 'white_label'])));
                if ($cache) {
                    Cache::put($cache_key, $clients, 60);
                }
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }

        return $clients ?? [];
    }

    public static function get_client(string $clientId = ''): array
    {
        if (empty($clientId)) {
            $clientId = ClientHelper::clientId();
        }

        $cache_key = "bucket/clients/" . $clientId . ".json";
        $client = Cache::get($cache_key);
        if ($client && is_array($client)) {
            return $client;
        }

        try {
            $path = "clients/" . $clientId . ".json";
            $backet_name = "serving_logs";

            $json = BucketHelper::get($path, $backet_name);
            $client = null;
            if (is_string($json) && !empty($json)) {
                $client = json_decode($json, true);
            }
            if (isset($client) && is_array($client)) {
                Cache::put($cache_key, $client, 60);
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }

        return $client ?? [];
    }


    /**
     * get_integrations
     *
     * @return array
     */
    public static function get_integrations(): array
    {
        $client = self::get_client();
        if (isset($client) && !empty($client)) {
            return $client["integrations"] ?? [];
        }
        return [];
    }

    /**
     * get_connection_string
     *
     * @return array
     */
    public static function get_connection_string(): string
    {
        $client = self::get_client();
        if (isset($client) && !empty($client)) {
            if (!empty($client["db_connection"] ?? [])) {
            } else if (!empty($client["private_connection"] ?? [])) {
            }
        }
        return '';
    }

    public static function is_features(string $type, $features): bool
    {
        $client = self::get_client();
        if (isset($client) && !empty($client)) {
            if (is_array($features)) {
                foreach ($features as $feature) {
                    if (!empty($feature) && in_array($feature, $client[$type] ?? [])) {
                        return true;
                    }
                }
            } else if (is_string($features) && !empty($features)) {
                return in_array($features, $client[$type] ?? []);
            } else {
                throw new InvalidArgumentException('$features should be string or array');
            }
        }
        return false;
    }

    /**
     * is_public_features
     *
     * @param mixed $features
     * @return boolean
     */
    public static function is_public_features($features): bool
    {
        return self::is_features('public_features', $features);
    }

    /**
     * is_private_features
     *
     * @param mixed $features
     * @return boolean
     */
    public static function is_private_features($features): bool
    {
        return self::is_features('private_features', $features);
    }
}
