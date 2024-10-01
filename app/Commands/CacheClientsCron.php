<?php

namespace App\Commands;

use Exception;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CacheClientsCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cacheclients:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache Clients Cron';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function syncClientsJson(array $clients): void
    {
        $file_name = 'clients.json';
        $current_clients = json_decode(Storage::disk('local')->get($file_name), true);
        $new_clients = json_encode($clients);

        if (serialize($current_clients ?? []) != serialize($clients ?? []) || empty($clients ?? [])) {
            Storage::disk('local')->put($file_name, $new_clients);
            Cache::forget('clients');
            // Log::info("Cron Cache Clients!");
        }
    }

    private function generateApiConfig(string $publicPath, string $serverName, string $serverAdmin = 'webmaster@localhost'): string
    {
        return '
                <VirtualHost *:80>
                    <Directory ' . $publicPath . '>
                        Options Indexes FollowSymLinks
                        AllowOverride All
                        Require all granted
                        Allow from all
                    </Directory>
                    ServerName ' . $serverName . '
                    ServerAdmin ' . $serverAdmin . '
                    DirectoryIndex index.php
                    DocumentRoot ' . $publicPath . '

                    ErrorLog ${APACHE_LOG_DIR}/' . $serverName . '_error.log
                    CustomLog ${APACHE_LOG_DIR}/' . $serverName . '_access.log combined

                </VirtualHost>
                <VirtualHost *:443>
                    <Directory ' . $publicPath . '>
                        Options Indexes FollowSymLinks
                        AllowOverride All
                        Require all granted
                        Allow from all
                    </Directory>
                    ServerName ' . $serverName . '
                    ServerAdmin ' . $serverAdmin . '
                    DirectoryIndex index.php
                    DocumentRoot ' . $publicPath . '

                    ErrorLog ${APACHE_LOG_DIR}/' . $serverName . '_error.log
                    CustomLog ${APACHE_LOG_DIR}/' . $serverName . '_access.log combined

                </VirtualHost>
                ';
    }

    private function generateUIConfig(string $publicApiPath, string $publicPath, string $serverName, string $serverAdmin = 'webmaster@localhost'): string
    {
        return '
                <VirtualHost *:80>
                    <Directory ' . $publicPath . '>
                        Options Indexes FollowSymLinks
                        AllowOverride All
                        Require all granted
                        Allow from all
                    </Directory>
                    ServerName ' . $serverName . '
                    ServerAdmin ' . $serverAdmin . '
                    DocumentRoot ' . $publicPath . '
                    DirectoryIndex index.php index.html
                    Alias /api ' . $publicApiPath . '

                    ErrorLog ${APACHE_LOG_DIR}/' . $serverName . '_error.log
                    CustomLog ${APACHE_LOG_DIR}/' . $serverName . '_access.log combined

                </VirtualHost>
                <VirtualHost *:443>
                    <Directory ' . $publicPath . '>
                        Options Indexes FollowSymLinks
                        AllowOverride All
                        Require all granted
                        Allow from all
                    </Directory>
                    ServerName ' . $serverName . '
                    ServerAdmin ' . $serverAdmin . '
                    DirectoryIndex index.php index.html
                    DocumentRoot ' . $publicPath . '
                    Alias /api ' . $publicApiPath . '

                    ErrorLog ${APACHE_LOG_DIR}/' . $serverName . '_error.log
                    CustomLog ${APACHE_LOG_DIR}/' . $serverName . '_access.log combined

                </VirtualHost>
                ';
    }

    private function syncApacheConfigs(array $clients): void
    {
        $needs_apache_reload = false;
        $apache_valid_paths = [];
        foreach ($clients as $client) {
            $clientId = (string)$client['_id'];
            $active = (int)($client['status'] ?? 0);

            $ui_domain = empty($client['ui_domain'] ?? '') ? $client['crm_domain'] ?? '' : $client['ui_domain'] ?? '';
            $api_domain = $client['api_domain'] ?? '';

            $ui_domain = str_replace('https://', '', str_replace('http://', '', $ui_domain));
            $api_domain = str_replace('https://', '', str_replace('http://', '', $api_domain));

            if ($active == 1) {
                if (!empty($api_domain) && false !== filter_var('https://' . $api_domain, FILTER_VALIDATE_URL)) {
                    $urlparts = parse_url('https://' . $api_domain);
                    if (!empty($urlparts['host'] ?? '')) {
                        $config_file_name = $clientId . '_' . $urlparts['host'] . '.conf';
                        $config_file_path = 'apache/' . $config_file_name;
                        $apache_valid_paths[] = $config_file_name;

                        $current = Storage::disk('local')->get($config_file_path);
                        $new = $this->generateApiConfig(public_path(), $urlparts['host']);
                        if (trim($current) != trim($new)) {
                            Storage::disk('local')->put($config_file_path, $new);
                            Log::info("Cron Saved Apache API config [$clientId]!");
                            $needs_apache_reload = true;
                        }
                    }
                }
                if (!empty($ui_domain) && false !== filter_var('https://' . $ui_domain, FILTER_VALIDATE_URL)) {
                    $urlparts = parse_url('https://' . $ui_domain);
                    if (!empty($urlparts['host'] ?? '')) {
                        $config_file_name = $clientId . '_' . $urlparts['host'] . '.conf';
                        $config_file_path = 'apache/' . $config_file_name;
                        $path_ui = config('app.path_ui');
                        $apache_valid_paths[] = $config_file_name;

                        if (!empty($path_ui) && file_exists($path_ui)) {
                            $current = Storage::disk('local')->get($config_file_path);
                            $new = $this->generateUIConfig(public_path(), $path_ui, $urlparts['host']);
                            if (trim($current) != trim($new)) {
                                Storage::disk('local')->put($config_file_path, $new);
                                $needs_apache_reload = true;
                                Log::info("Cron Saved Apache UI config  [$clientId]!");
                            }
                        } else {
                            Log::error("Cron Apache: app.path_ui is empty");
                        }
                    }
                }
            }
        }

        $apache_storage_path = storage_path('app') . '/apache/';
        $apache_storage_files = array_diff(scandir($apache_storage_path), array('.', '..'));
        foreach ($apache_storage_files as $file) {
            if (!in_array($file, $apache_valid_paths)) {
                unlink($apache_storage_path . $file);
                $needs_apache_reload = true;
                Log::info("Cron Removed Apache config: $apache_storage_path$file!");
            }
        }

        if ($needs_apache_reload) {
            $output = null;
            $resultCode = null;
            $cmd = 'systemctl reload apache2';
            try {
                exec($cmd, $output, $resultCode);
            } catch (Exception $ex) {
            }
            Log::info("Restart Apache (systemctl reload apache2) Returned with status $resultCode and output:\n" . print_r($output, true));
        }
    }

    private function getEnvironmentValue(string $envFile, string $keyValue): string
    {
        // Read .env-file
        $env = file_get_contents($envFile);

        // Split string on every " " and write into array
        $env = preg_split('/\n+/', $env);

        // Loop through given data
        foreach ($env as $k => $line) {
            $line = strtoupper($line);
            $keyPosition = strpos($line, "{$keyValue}=");
            $valBefore = $keyPosition !== false && $keyPosition >= 0 ? substr($line, 0, $keyPosition) : '';
            if ($keyPosition !== false && strpos($valBefore, '#') === false) {
                // Turn the value into an array and stop after the first split
                // So it's not possible to split e.g. the App-Key by accident
                $entry = explode("=", $line, 2);

                // Check, if new key fits the actual .env-key
                if ($entry[0] == strtoupper($keyValue)) {
                    return trim(trim($entry[1], "'"), '"');
                }
            }
        }
        return '';
    }

    private function setEnvironmentValue(string $envFile, array $keyValue): void
    {
        $newlyInserted = true;

        // Read .env-file
        $env = file_get_contents($envFile);

        // Split string on every " " and write into array
        $env = preg_split('/\n+/', $env);

        $changed = false;
        // Loop through given data
        foreach ($keyValue as $key => $value) {

            if (!empty($key)) {

                switch (gettype($value)) {
                    case 'array': {
                            $value = '\'' . json_encode($value) . '\'';
                            break;
                        }
                    case 'string': {
                            $value = '\'' . $value . '\'';
                            break;
                        }
                    case 'boolean': {
                            $value = $value ? 'true' : 'false';
                            break;
                        }
                }

                $find = false;
                // Loop through .env-data
                foreach ($env as $k => $line) {

                    $line = strtoupper($line);
                    $keyPosition = strpos($line, "{$key}=");
                    $valBefore = $keyPosition !== false && $keyPosition >= 0 ? substr($line, 0, $keyPosition) : '';
                    if ($keyPosition !== false && strpos($valBefore, '#') === false) {
                        // Turn the value into an array and stop after the first split
                        // So it's not possible to split e.g. the App-Key by accident
                        $entry = explode("=", $line, 2);

                        // Check, if new key fits the actual .env-key
                        if ($entry[0] == strtoupper($key)) {
                            // If yes, overwrite it with the new one
                            $env[$k] = $key . "=" . $value;
                            $find = $changed = true;
                            break;
                        }
                    }
                }

                if (!$find && $newlyInserted) {
                    $env[] = $key . "=" . $value;
                }
            }
        }

        if ($changed) {
            // Turn the array back to an String
            $env = implode("\n", $env);

            $fp = fopen($envFile, 'w');
            fwrite($fp, $env);
            fclose($fp);
        }
    }

    private function getConnectionString(array $db): string
    {
        if (is_array($db)) {
            $status = (int)($db['a1'] ?? 0); //Status
            if ($status == 1) {
                $type = $db['a2'] ?? '';  //Shared | Private
                $domain = $db['a3'] ?? '';
                $username = $db['a4'] ?? '';
                $password = $db['a5'] ?? '';
                $database = $db['a6'] ?? 'test';
                $DB_URI = "mongodb+srv://$username:$password@$domain/$database?retryWrites=true&w=majority";
                // check
                try {
                    $client = new \MongoDB\Client($DB_URI);
                    $client->listDatabases();
                    return $DB_URI;
                } catch (Exception $ex) {
                    return '';
                }
            }
        }
        return '';
    }

    private function syncEnvironment(array $clients): void
    {
        $basedir = ($_ENV['APP_BASE_PATH'] ?? dirname(dirname(__DIR__))) . '/';
        foreach ($clients as $client) {
            $clientId = (string)$client['_id'];
            $client_env = '.env.' . $clientId . '.php';
            $active = (int)($client['status'] ?? 0);
            if ($active == 1) {
                $db_connection = $client['db_connection'] ?? [];
                $DB_URI = $this->getConnectionString($db_connection);
                
                $domain = $db_connection['a3'] ?? '';
                $username = $db_connection['a4'] ?? '';
                $password = $db_connection['a5'] ?? '';
                $database = $db_connection['a6'] ?? 'test';

                $DB_DATABASE_CRYPT = $DB_DATABASE_RENAME_META = (bool)($db_connection['a7'] ?? false);

                $host = $db_connection['a3'] ?? '';
                $username = $db_connection['a4'] ?? '';
                $password = $db_connection['a5'] ?? '';
                $database = $db_connection['a6'] ?? 'test';

                $envArray = [
                    'APP_NAME' => 'Client_' . $clientId,
                    'APP_ENV' => $clientId,
                    'APP_URL' => $client['api_domain'] ?? '',
                    'L5_SWAGGER_BASE_PATH' => $client['api_domain'] ?? '',
                    'CACHE_PREFIX' => ($client['client_type'] ?? 'crm') . '_' . $clientId . '_cache_',
                    'LOG_CHANNEL' => 'stack',
                    'DB_CONNECTION' => 'mongodb',
                    'DB_URI' => $DB_URI,
                    'DB_HOST' => $host,
                    'DB_PORT' => 27017,
                    'DB_DATABASE' => $database,
                    'DB_USERNAME' => $username,
                    'DB_PASSWORD' => $password,
                    'DB_DATABASE_CRYPT' => $DB_DATABASE_CRYPT,
                    'DB_DATABASE_RENAME_META' => $DB_DATABASE_RENAME_META,
                ];

                if ($DB_DATABASE_CRYPT) {
                    $envArray['CRYPT_KEY'] = $client['db_crypt_key'] ?? '';
                    $envArray['CRYPT_IV'] = $client['db_crypt_iv'] ?? '';
                }

                if (!file_exists($basedir . $client_env)) {
                    $env = $basedir . '.env';
                    if (file_exists($env)) {
                        $bak = $basedir . 'bak' . $client_env;
                        if (file_exists($bak)) {
                            unlink($bak);
                        }
                        copy($env, $bak);
                        $this->setEnvironmentValue($bak, $envArray);
                        rename($bak, $basedir . $client_env);
                    }
                } else {
                    $this->setEnvironmentValue($basedir . $client_env, $envArray);
                }
            }
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $clients = Client::query()->whereIn('status', ['1', 1, true])->whereIn('client_type', ['saas', 'white_label'])->get()->toArray();
        $this->syncClientsJson($clients);
        $this->syncApacheConfigs($clients);
        $this->syncEnvironment($clients);
    }
}
