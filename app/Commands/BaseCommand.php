<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class BaseCommand extends Command
{
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function getConnectionString(array $db): string
    {
        $DB_URI = '';
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
                $client = new \MongoDB\Client($DB_URI);
                $client->listDatabases();
            }
        }
        return $DB_URI;
    }

    protected function setDBConnection(array $client): void
    {
        $db_connection = $client['db_connection'] ?? [];
        $DB_URI = $this->getConnectionString($db_connection);

        $domain = $db['a3'] ?? '';
        $username = $db['a4'] ?? '';
        $password = $db['a5'] ?? '';
        $database = $db['a6'] ?? 'test';

        $DB_DATABASE_CRYPT = $DB_DATABASE_RENAME_META = (bool)($db_connection['a7'] ?? false);

        if (!empty($DB_URI)) {
            Config::set("database.default", 'mongodb');
            Config::set("database.connections.mongodb", [
                'driver' => 'mongodb',
                'dsn' => $DB_URI,
                'database' => $database,
                'host' => $domain,
                'port' => 27017,
                'username' => $username,
                'password' => $password
            ]);

            Config::set("crypt.database.enable", $DB_DATABASE_CRYPT);
            Config::set("crypt.database.rename_meta", $DB_DATABASE_RENAME_META);

            Config::set("crypt.key", $client['db_crypt_key'] ?? '');
            Config::set("crypt.iv", $client['db_crypt_iv'] ?? '');
        } else throw new \Exception("ClientId [" . $client['_id'] . "] has empty connection string");
    }
}
