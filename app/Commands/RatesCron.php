<?php

namespace App\Commands;

use App\Commands\BaseCommand;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Facades\Log;

class RatesCron extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rates:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Rates';

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
        try {
            $d = new CurrencyHelper();
            $d->Sync('btc');
            Log::info("Cron Sync Rates!");
        } catch (\Exception $ex) {
            Log::info("Cron Sync Rates Error: " . $ex->getMessage());
        }
    }
}
