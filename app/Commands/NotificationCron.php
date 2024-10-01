<?php

namespace App\Commands;

use App\Models\User;
use App\Models\Client;
use App\Classes\Access;
use App\Commands\BaseCommand;
use App\Models\NotificationMessage;
use Illuminate\Support\Facades\Log;
use App\Events\NotificationUserEvent;
use App\Repository\Notifications\NotificationsRepository;

class NotificationCron extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifications Cron';

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
        $clients = Client::all(['_id', 'nickname', 'db_connection', 'db_crypt_key', 'db_crypt_iv']);

        foreach ($clients as $client) {

            $this->setDBConnection($client->toArray());

            $users = User::query()->where('clientId', '=', $client->_id)->get(['_id', 'permissions', 'roles']);

            $rep = new NotificationsRepository();

            $all_messsage_data = $rep->notifications(false);

            foreach ($users as $user) {

                Access::attach_custom_access($user);

                $messsage_data = [];
                foreach ($all_messsage_data as $m) {
                    $allow = true;
                    foreach ($m['permissions'] ?? [] as $permission) {
                        if (strpos($permission, 'role:') !== false) {
                            $allow = $allow || $user->hasAnyRoles([str_replace('role:', '', $permission)]);
                        } else {
                            $allow = $allow || $user->hasAnyPermissions([$permission]);
                        }
                    }
                    if ($allow) {
                        $messsage_data[] = $m;
                    }
                }

                if (count($messsage_data) > 0) {
                    $message = new NotificationMessage($user->_id, $messsage_data);
                    event(new NotificationUserEvent($message));
                }
            }
        }
        Log::info("Cron Notifications!");
    }
}
