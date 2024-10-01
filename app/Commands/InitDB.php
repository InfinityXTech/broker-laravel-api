<?php

namespace App\Commands;

use App\Models\User;
use App\Models\Client;
use App\Scopes\ClientScope;
use App\Commands\BaseCommand;
use App\Repository\Users\UserRepository;

class InitDB extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'initdb:user {email} {password} {client_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'InitDB';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $clients = [];
        $client_id = $this->argument('client_id');
        if (empty($client_id)) {
            $clients = Client::all(['_id', 'db_connection', 'db_crypt_key', 'db_crypt_iv'])->toArray();
        } else {
            $clients = Client::query()->where('_id', '=', $client_id)->get(['_id', 'db_connection', 'db_crypt_key', 'db_crypt_iv'])->toArray();
        }
        $email = $this->argument('email');
        $password = $this->argument('password');
        if (!empty($email) && !empty($password)) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            foreach ($clients as $client) {

                try {
                    $this->setDBConnection($client);

                    $name = ucfirst(explode('@', $email)[0]);
                    $payload = [
                        'status' => '1',
                        'clientId' => $client_id,
                        'name' => $name,
                        'account_email' => $email,
                        'systemId' => 'crm',
                        'skype' => '',
                        'roles' => ['admin', 'super_admin'],
                    ];

                    $query = User::query()->withoutGlobalScope(new ClientScope)->where('clientId', '=', $client['_id'])->where('account_email', '=', $email);
                    if (!$query->exists()) {
                        User::query()->withoutGlobalScope(new ClientScope)->where('account_email', '=', $email)->delete();

                        $rep = new UserRepository(new User());
                        $payload['username'] = $payload['username'] ?? $payload['name'];
                        list($username, $password, $secret, $qrCodeUrl) = $rep->getUsernameAndPassword($payload['username']);
                        $payload['username'] = $username;
                        $payload['password'] = $password;
                        $payload['qr_secret'] = $secret;
                        $payload['qr_img'] = $qrCodeUrl;

                        $model = $rep->create($payload);
                        $rep->reset_password($model->_id, $password);
                        $output->writeln("ClientId [" . $client['_id'] . "]: created => $model->_id, qr code " . $model->qr_img);
                    } else {
                        $model = $query->get()->first();
                        $user = $model->toArray();
                        $output->writeln("User: " . print_r($user, true));
                        $model->update(['password' => bcrypt($password)]);
                        $output->writeln("ClientId [" . $client['_id'] . "]: updated => $model->_id, qr code " . $model->qr_img);
                    }
                } catch (\Exception $ex) {
                    $output->writeln("ClientId [" . $client['_id'] . "] " . $ex->getMessage());
                }
            }
        }
    }
}
