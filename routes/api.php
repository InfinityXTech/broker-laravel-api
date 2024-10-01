<?php

use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;

use App\Events\NotificationEvent;
use App\Models\NotificationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Notifications\SlackNotification;
use App\Http\Controllers\CRM\CRMController;
use App\Notifications\BroadcastNotification;
use Illuminate\Support\Facades\Notification;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Logs\LogsController;
use App\Http\Controllers\Test\TestController;
use App\Http\Controllers\Forms\FormController;

use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Leads\LeadsController;
use App\Http\Controllers\Utils\UtilsController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Brokers\BrokerController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Masters\MasterController;
use App\Notifications\Slack\Messages\SlackMessage;
use App\Http\Controllers\FireFTD\FireFTDController;
use App\Http\Controllers\Gravity\GravityController;
use App\Http\Controllers\Storage\StorageController;
use App\Http\Controllers\Support\SupportController;

use App\Http\Controllers\Billings\BillingsController;
use App\Http\Controllers\Planning\PlanningController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Validate\ValidateController;
use App\Http\Controllers\Brokers\BrokerCapsController;
use App\Http\Controllers\Campaigns\CampaignsController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Affiliates\AffiliatesController;
use App\Http\Controllers\Brokers\BrokerBillingController;
use App\Http\Controllers\Brokers\BrokerPayoutsController;
use App\Http\Controllers\Brokers\BrokerSpyToolController;
use App\Http\Controllers\Masters\MasterBillingController;

use App\Http\Controllers\Masters\MasterPayoutsController;

use App\Http\Controllers\Advertisers\AdvertisersController;

use App\Http\Controllers\ClickReport\ClickReportController;
use App\Http\Controllers\Dictionaries\DictionaryController;
use App\Http\Controllers\Investigate\InvestigateController;
use App\Http\Controllers\LeadsReview\LeadsReviewController;
use App\Http\Controllers\Performance\PerformanceController;
use App\Http\Controllers\Campaigns\CampaignsBudgetController;
use App\Http\Controllers\ResyncReport\ResyncReportController;
use App\Http\Controllers\Brokers\BrokerIntegrationsController;
use App\Http\Controllers\Brokers\BrokerPrivateDealsController;
use App\Http\Controllers\Campaigns\CampaignsPayoutsController;
use App\Http\Controllers\Affiliates\AffiliateBillingController;
use App\Http\Controllers\EmailsChecker\EmailsCheckerController;
use App\Http\Controllers\Notifications\NotificationsController;
use App\Http\Controllers\QualityReport\QualityReportController;
use App\Http\Controllers\TagManagement\TagManagementController;
use App\Http\Controllers\Brokers\BrokerBillingEntitiesController;
use App\Http\Controllers\Brokers\BrokerStatusManagmentController;
use App\Http\Controllers\Campaigns\CampaignsLimitationController;
use App\Http\Controllers\MarketingSuite\MarketingSuiteController;
use App\Http\Controllers\Masters\MasterBillingEntitiesController;
use App\Http\Controllers\Advertisers\AdvertisersBillingController;
use App\Http\Controllers\Brokers\BrokerBillingAdjustmentController;
use App\Http\Controllers\Brokers\BrokerBillingChargebackController;
use App\Http\Controllers\Campaigns\CampaignsPrivateDealsController;
use App\Http\Controllers\MarketingReport\MarketingReportController;
use App\Http\Controllers\Masters\MasterBillingAdjustmentController;
use App\Http\Controllers\Masters\MasterBillingChargebackController;
use App\Http\Controllers\Performance\PerformanceSettingsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointController;
use App\Http\Controllers\Advertisers\AdvertisersPostEventsController;
use App\Http\Controllers\MarketingGravity\MarketingGravityController;
use App\Http\Controllers\Brokers\BrokerBillingPaymentMethodController;
use App\Http\Controllers\Masters\MasterBillingPaymentMethodController;
use App\Http\Controllers\Affiliates\AffiliateBillingEntitiesController;
use App\Http\Controllers\Brokers\BrokerBillingPaymentRequestController;
use App\Http\Controllers\MarketingBillings\MarketingBillingsController;
use App\Http\Controllers\Masters\MasterBillingPaymentRequestController;
use App\Http\Controllers\Affiliates\AffiliateBillingAdjustmentController;
use App\Http\Controllers\Affiliates\AffiliateBillingChargebackController;
use App\Http\Controllers\Campaigns\CampaignsTargetingLocationsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointScrubController;
use App\Http\Controllers\Advertisers\AdvertisersBillingEntitiesController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointPayoutsController;
use App\Http\Controllers\Advertisers\AdvertisersBillingAdjustmentController;
use App\Http\Controllers\Advertisers\AdvertisersBillingChargebackController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointSecurityController;
use App\Http\Controllers\Affiliates\AffiliateBillingPaymentMethodsController;
use App\Http\Controllers\MarketingInvestigate\MarketingInvestigateController;
use App\Http\Controllers\Affiliates\AffiliateBillingGeneralBalancesController;
use App\Http\Controllers\Affiliates\AffiliateBillingPaymentRequestsController;
use App\Http\Controllers\Brokers\BrokerBillingCompletedTransactionsController;
use App\Http\Controllers\Masters\MasterBillingCompletedTransactionsController;
use App\Http\Controllers\Advertisers\AdvertisersBillingPaymentMethodController;
use App\Http\Controllers\Advertisers\AdvertisersBillingPaymentRequestController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointPrivateDealsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingEntitiesController;
use App\Http\Controllers\Affiliates\AffiliateBillingCompletedTransactionsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingAdjustmentController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingChargebackController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointSubPublisherTokensController;
use App\Http\Controllers\Advertisers\AdvertisersBillingCompletedTransactionsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingPaymentMethodsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointDynamicIntegrationIDsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingGeneralBalancesController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingPaymentRequestsController;
use App\Http\Controllers\TrafficEndpoints\TrafficEndpointBillingCompletedTransactionsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::get('/test', function () {
//     Notification::route('slack', '')->notify(new SlackNotification(new SlackMessage('tech_support', 'test message')));
//     // GeneralHelper::PrintR([ClientHelper::clientId()]);die();
// });

// Route::get('/broadcast/test', function () {

//     // New Pusher instance with our config data

//     // return print_r(config('broadcasting'), true);

//     $messsage_data = ['test'];
//     $message = new NotificationMessage(Auth::id(), $messsage_data);
//     event(new NotificationEvent($message));
//     // broadcast(new NotificationEvent($message))->toOthers();
//     // NotificationEvent::dispatch($message);

//     // $user = User::all()->first();
//     // $d = new BroadcastNotification($message);
//     // $user->notify($d->toBroadcast(null));

//     // Event::fire('notifications.user.' . Auth::id(), [new NotificationEvent($message)]);
//     return 'ok=' . time(); // . print_r($d, true);

//     $app_id = config('broadcasting.connections.pusher.app_id');
//     $app_key = config('broadcasting.connections.pusher.key');
//     $app_secret = config('broadcasting.connections.pusher.secret');
//     $app_cluster = config('broadcasting.connections.pusher.options.cluster');

//     $pusher = new Pusher\Pusher($app_key, $app_secret, $app_id, ['cluster' => $app_cluster]);

//     // Enable pusher logging - I used an anonymous class and the Monolog
//     // $pusher->set_logger(new class {
//     //        public function log($msg)
//     //        {
//     //              Log::info($msg);
//     //        }
//     // });

//     // Your data that you would like to send to Pusher
//     $data = ['text' => 'hello world from Laravel 5.3'];

//     // Sending the data to channel: "test_channel" with "my_event" event
//     $pusher->trigger('notifications.user.604f6dc57f976bfaca2f493e', 'message', $data);

//     return 'ok';
// });

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/google', [AuthController::class, 'google']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('ga');
    Route::post('/renewal', [AuthController::class, 'renewal']);
    Route::get('/profile', [AuthController::class, 'userProfile']);
    Route::get('/user_profile_data/{name}', [AuthController::class, 'getUserProfileData']);
    Route::put('/user_profile_data/{name}', [AuthController::class, 'setUserProfileData']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'forms'
], function () {
    Route::get('/all', [FormController::class, 'index']);
    Route::post('/create', [FormController::class, 'create']);
    Route::get('/{id}', [FormController::class, 'get']);
    Route::put('/update/{id}', [FormController::class, 'update']);
    Route::delete('/delete/{id}', [FormController::class, 'delete']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'dictionary'
], function () {
    Route::get('/', [DictionaryController::class, 'index']);
    Route::get('/brokers', [DictionaryController::class, 'brokers']);
    Route::get('/traffic_endpoints', [DictionaryController::class, 'traffic_endpoints']);
    Route::get('/countries', [DictionaryController::class, 'countries']);
    Route::get('/languages', [DictionaryController::class, 'languages']);
    Route::get('/countries_and_languages', [DictionaryController::class, 'countries_and_languages']);
    Route::get('/currency_rates', [DictionaryController::class, 'currency_rates']);
    Route::post('/currency_rates', [DictionaryController::class, 'currency_rates']);
    Route::get('/marketing_post_events', [DictionaryController::class, 'marketing_post_events']);
    Route::get('/regions', [DictionaryController::class, 'regions']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'broker',
    // 'namespace' => 'Admin'
], function () {
    Route::get('/broker_caps', [BrokerCapsController::class, 'all_caps_get']);
    Route::post('/broker_caps', [BrokerCapsController::class, 'all_caps_post']);
    Route::get('/broker_caps/dictionaries', [BrokerCapsController::class, 'all_caps_dictionaries']);
    Route::get('/broker_caps/countries', [BrokerCapsController::class, 'cap_countries']);
    Route::get('/broker_caps/{id}', [BrokerCapsController::class, 'get']);
    Route::get('/broker_caps/logs/{id}', [BrokerCapsController::class, 'logs']);
    Route::get('/broker_caps/available_endpoints/{id}', [BrokerCapsController::class, 'available_endpoints']);
    Route::put('/broker_caps/enable/{id}', [BrokerCapsController::class, 'enable']);
    Route::put('/broker_caps/update/{id}', [BrokerCapsController::class, 'update']);

    Route::get('/spy_tool/brokers_and_integrations/{leadId}', [BrokerSpyToolController::class, 'brokers_and_integrations']);
    Route::post('/spy_tool/run', [BrokerSpyToolController::class, 'run']);

    Route::get('/all', [BrokerController::class, 'index']);
    Route::post('/create', [BrokerController::class, 'create']);
    Route::post('/un_payable_leads', [BrokerController::class, 'un_payable_leads']);
    Route::get('/{id}', [BrokerController::class, 'get']);
    Route::get('/name/{id}', [BrokerController::class, 'get_name']);
    Route::put('/update/{id}', [BrokerController::class, 'update']);
    Route::delete('/patch/{id}', [BrokerController::class, 'archive']);

    Route::get('/price/download', [BrokerController::class, 'download_price']);
    Route::get('/crgdeals/download', [BrokerController::class, 'download_crgdeals']);

    Route::get('/{brokerId}/conversion_rates', [BrokerController::class, 'conversion_rates']);

    Route::get('/{brokerId}/integrations/all', [BrokerIntegrationsController::class, 'index']);
    Route::get('/{brokerId}/integrations/active', [BrokerIntegrationsController::class, 'active']);
    Route::post('/{brokerId}/integrations/create', [BrokerIntegrationsController::class, 'create']);
    Route::get('/{brokerId}/integrations/{id}', [BrokerIntegrationsController::class, 'get']);
    Route::put('/{brokerId}/integrations/update/{id}', [BrokerIntegrationsController::class, 'update']);
    Route::delete('/{brokerId}/integrations/delete/{id}', [BrokerIntegrationsController::class, 'delete']);

    Route::get('/{brokerId}/status_managment/all', [BrokerStatusManagmentController::class, 'index']);
    Route::post('/{brokerId}/status_managment/create', [BrokerStatusManagmentController::class, 'create']);
    Route::get('/{brokerId}/status_managment/{id}', [BrokerStatusManagmentController::class, 'get']);
    Route::put('/{brokerId}/status_managment/update/{id}', [BrokerStatusManagmentController::class, 'update']);
    Route::delete('/{brokerId}/status_managment/delete/{id}', [BrokerStatusManagmentController::class, 'delete']);

    Route::get('/{brokerId}/payouts/all', [BrokerPayoutsController::class, 'index']);
    Route::post('/{brokerId}/payouts/create', [BrokerPayoutsController::class, 'create']);
    Route::get('/{brokerId}/payouts/{id}', [BrokerPayoutsController::class, 'get']);
    Route::get('/{brokerId}/payouts/logs/{id}', [BrokerPayoutsController::class, 'log']);
    Route::put('/{brokerId}/payouts/enable/{id}', [BrokerPayoutsController::class, 'enable']);
    Route::put('/{brokerId}/payouts/update/{id}', [BrokerPayoutsController::class, 'update']);
    Route::delete('/{brokerId}/payouts/delete/{id}', [BrokerPayoutsController::class, 'delete']);

    Route::get('/{brokerId}/private_deals/all', [BrokerPrivateDealsController::class, 'index']);
    Route::post('/{brokerId}/private_deals/create', [BrokerPrivateDealsController::class, 'create']);
    Route::get('/{brokerId}/private_deals/{id}', [BrokerPrivateDealsController::class, 'get']);
    Route::get('/{brokerId}/private_deals/logs/{id}', [BrokerPrivateDealsController::class, 'logs']);
    Route::put('/{brokerId}/private_deals/update/{id}', [BrokerPrivateDealsController::class, 'update']);
    Route::delete('/{brokerId}/private_deals/delete/{id}', [BrokerPrivateDealsController::class, 'delete']);

    Route::get('/{brokerId}/daily_cr/', [BrokerController::class, 'daily_cr']);

    Route::get('/{brokerId}/broker_caps', [BrokerCapsController::class, 'all_caps_get']);
    Route::post('/{brokerId}/broker_caps', [BrokerCapsController::class, 'all_caps_post']);
    Route::get('/{brokerId}/broker_caps/dictionaries', [BrokerCapsController::class, 'all_caps_dictionaries']);

    Route::get('/{brokerId}/broker_caps/countries', [BrokerCapsController::class, 'cap_countries']);
    Route::post('/{brokerId}/broker_caps/create', [BrokerCapsController::class, 'create']);

    Route::get('/{brokerId}/billing/general/balance', [BrokerBillingController::class, 'general_balance']);
    Route::get('/{brokerId}/billing/general/balance/logs', [BrokerBillingController::class, 'general_balance_logs']);
    Route::post('/{brokerId}/billing/general/balance/logs', [BrokerBillingController::class, 'post_general_balance_logs']);

    Route::get('/{brokerId}/billing/recalculate/logs', [BrokerBillingController::class, 'general_recalculate_logs']);
    Route::post('/{brokerId}/billing/recalculate/logs', [BrokerBillingController::class, 'post_general_recalculate_logs']);

    Route::put('/{brokerId}/billing/general/balance/logs/update/{logId}', [BrokerBillingController::class, 'update_general_balance_logs']);
    Route::put('/{brokerId}/billing/general/settings/negative_balance', [BrokerBillingController::class, 'settings_negative_balance'])->middleware('ga');
    Route::put('/{brokerId}/billing/general/settings/credit_amount', [BrokerBillingController::class, 'settings_credit_amount'])->middleware('ga');
    Route::get('/{brokerId}/billing/general/logs', [BrokerBillingController::class, 'logs']);
    Route::post('/{brokerId}/billing/general/logs', [BrokerBillingController::class, 'logs']);
    Route::put('/{brokerId}/billing/manual_status', [BrokerBillingController::class, 'manual_status']);

    Route::get('/{brokerId}/billing/entities/all', [BrokerBillingEntitiesController::class, 'index']);
    Route::post('/{brokerId}/billing/entities/create', [BrokerBillingEntitiesController::class, 'create'])->middleware('ga');
    Route::get('/{brokerId}/billing/entities/{id}', [BrokerBillingEntitiesController::class, 'get']);
    Route::post('/{brokerId}/billing/entities/update/{id}', [BrokerBillingEntitiesController::class, 'update'])->middleware('ga');
    Route::delete('/{brokerId}/billing/entities/delete/{id}', [BrokerBillingEntitiesController::class, 'delete'])->middleware('ga');

    Route::get('/{brokerId}/billing/our_payment_methods/all', [BrokerBillingPaymentMethodController::class, 'our_index']);
    Route::put('/{brokerId}/billing/our_payment_methods/select/{id}', [BrokerBillingPaymentMethodController::class, 'our_select']);

    Route::get('/{brokerId}/billing/payment_methods/all', [BrokerBillingPaymentMethodController::class, 'index']);
    Route::get('/{brokerId}/billing/payment_methods/{id}', [BrokerBillingPaymentMethodController::class, 'get']);
    Route::post('/{brokerId}/billing/payment_methods/create', [BrokerBillingPaymentMethodController::class, 'create']);
    Route::post('/{brokerId}/billing/payment_methods/update/{id}', [BrokerBillingPaymentMethodController::class, 'update']);
    Route::put('/{brokerId}/billing/payment_methods/select/{paymentMethodId}', [BrokerBillingPaymentMethodController::class, 'select']);
    Route::get('/{brokerId}/billing/payment_methods/files/{id}', [BrokerBillingPaymentMethodController::class, 'files']);

    Route::get('/{brokerId}/billing/payment_requests/all', [BrokerBillingPaymentRequestController::class, 'index']);
    Route::get('/{brokerId}/billing/payment_requests/completed', [BrokerBillingPaymentRequestController::class, 'completed']);
    Route::get('/{brokerId}/billing/payment_requests/{id}', [BrokerBillingPaymentRequestController::class, 'get']);
    Route::post('/{brokerId}/billing/payment_requests/pre_create_query', [BrokerBillingPaymentRequestController::class, 'pre_create_query']);
    Route::post('/{brokerId}/billing/payment_requests/create', [BrokerBillingPaymentRequestController::class, 'create']);
    Route::get('/{brokerId}/billing/payment_requests/calculations/{id}', [BrokerBillingPaymentRequestController::class, 'view_calculations']);
    Route::get('/{brokerId}/billing/payment_requests/invoice/{id}', [BrokerBillingPaymentRequestController::class, 'get_invoice']);
    Route::get('/{brokerId}/billing/payment_requests/files/{id}', [BrokerBillingPaymentRequestController::class, 'get_files']);
    Route::put('/{brokerId}/billing/payment_requests/approve/{id}', [BrokerBillingPaymentRequestController::class, 'approve']);
    Route::put('/{brokerId}/billing/payment_requests/change/{id}', [BrokerBillingPaymentRequestController::class, 'change']);
    Route::put('/{brokerId}/billing/payment_requests/reject/{id}', [BrokerBillingPaymentRequestController::class, 'reject']);
    Route::post('/{brokerId}/billing/payment_requests/fin_approve/{id}', [BrokerBillingPaymentRequestController::class, 'fin_approve']); // TODO ->middleware('ga');
    Route::put('/{brokerId}/billing/payment_requests/fin_reject/{id}', [BrokerBillingPaymentRequestController::class, 'fin_reject']);
    Route::put('/{brokerId}/billing/payment_requests/real_income/{id}', [BrokerBillingPaymentRequestController::class, 'real_income']);
    Route::put('/{brokerId}/billing/payment_requests/archive/{id}', [BrokerBillingPaymentRequestController::class, 'archive']);

    Route::get('/{brokerId}/billing/completed_transactions/all', [BrokerBillingCompletedTransactionsController::class, 'index']);
    Route::post('/{brokerId}/billing/completed_transactions/create', [BrokerBillingCompletedTransactionsController::class, 'create']);

    Route::get('/{brokerId}/billing/adjustments/all', [BrokerBillingAdjustmentController::class, 'index']);
    Route::post('/{brokerId}/billing/adjustments/create', [BrokerBillingAdjustmentController::class, 'create'])->middleware('ga');
    Route::get('/{brokerId}/billing/adjustments/{id}', [BrokerBillingAdjustmentController::class, 'get']);
    Route::put('/{brokerId}/billing/adjustments/update/{id}', [BrokerBillingAdjustmentController::class, 'update'])->middleware('ga');
    Route::delete('/{brokerId}/billing/adjustments/delete/{id}', [BrokerBillingAdjustmentController::class, 'delete'])->middleware('ga');

    Route::get('/{brokerId}/billing/chargeback/all', [BrokerBillingChargebackController::class, 'index']);
    Route::post('/{brokerId}/billing/chargeback/create', [BrokerBillingChargebackController::class, 'create'])->middleware('ga');
    Route::get('/{brokerId}/billing/chargeback/{id}', [BrokerBillingChargebackController::class, 'get']);
    Route::post('/{brokerId}/billing/chargeback/update/{id}', [BrokerBillingChargebackController::class, 'update'])->middleware('ga');
    Route::delete('/{brokerId}/billing/chargeback/delete/{id}', [BrokerBillingChargebackController::class, 'delete'])->middleware('ga');
    Route::post('/{brokerId}/billing/chargeback/fin_approve/{id}', [BrokerBillingChargebackController::class, 'fin_approve']); // TODO ->middleware('ga');
    Route::put('/{brokerId}/billing/chargeback/fin_reject/{id}', [BrokerBillingChargebackController::class, 'fin_reject']);
    Route::get('/{brokerId}/billing/chargeback/files/{id}', [BrokerBillingChargebackController::class, 'files']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'master',
    // 'namespace' => 'Admin'
], function () {
    Route::get('/all', [MasterController::class, 'index']);
    Route::post('/create', [MasterController::class, 'create']);
    Route::get('/{id}', [MasterController::class, 'get']);
    Route::put('/update/{id}', [MasterController::class, 'update']);
    Route::delete('/patch/{id}', [MasterController::class, 'archive']);
    Route::put('/reset_password/{id}', [MasterController::class, 'reset_password']);

    Route::get('/{masterId}/payouts/all', [MasterPayoutsController::class, 'index']);
    Route::post('/{masterId}/payouts/create', [MasterPayoutsController::class, 'create']);
    Route::get('/{masterId}/payouts/{id}', [MasterPayoutsController::class, 'get']);
    Route::put('/{masterId}/payouts/enable/{id}', [MasterPayoutsController::class, 'enable']);
    Route::put('/{masterId}/payouts/update/{id}', [MasterPayoutsController::class, 'update']);
    Route::delete('/{masterId}/payouts/delete/{id}', [MasterPayoutsController::class, 'delete']);

    Route::get('/{masterId}/billing/general/balance', [MasterBillingController::class, 'general_balance']);
    Route::get('/{masterId}/billing/general/balance/logs', [MasterBillingController::class, 'general_balance_logs']);
    Route::get('/{masterId}/billing/general/logs', [MasterBillingController::class, 'logs']);
    Route::post('/{masterId}/billing/general/logs', [MasterBillingController::class, 'logs']);

    Route::get('/{masterId}/billing/entities/all', [MasterBillingEntitiesController::class, 'index']);
    Route::post('/{masterId}/billing/entities/create', [MasterBillingEntitiesController::class, 'create'])->middleware('ga');
    Route::get('/{masterId}/billing/entities/{id}', [MasterBillingEntitiesController::class, 'get']);
    Route::post('/{masterId}/billing/entities/update/{id}', [MasterBillingEntitiesController::class, 'update'])->middleware('ga');
    Route::delete('/{masterId}/billing/entities/delete/{id}', [MasterBillingEntitiesController::class, 'delete'])->middleware('ga');

    Route::get('/{masterId}/billing/payment_methods/all', [MasterBillingPaymentMethodController::class, 'index']);
    Route::get('/{masterId}/billing/payment_methods/{id}', [MasterBillingPaymentMethodController::class, 'get']);
    Route::put('/{masterId}/billing/payment_methods/select/{id}', [MasterBillingPaymentMethodController::class, 'select']);
    Route::post('/{masterId}/billing/payment_methods/create', [MasterBillingPaymentMethodController::class, 'create']);
    Route::post('/{masterId}/billing/payment_methods/update/{id}', [MasterBillingPaymentMethodController::class, 'update']);
    Route::get('/{masterId}/billing/payment_methods/files/{id}', [MasterBillingPaymentMethodController::class, 'files']);

    Route::get('/{masterId}/billing/payment_requests/all', [MasterBillingPaymentRequestController::class, 'index']);
    Route::get('/{masterId}/billing/payment_requests/completed', [MasterBillingPaymentRequestController::class, 'completed']);
    Route::get('/{masterId}/billing/payment_requests/{id}', [MasterBillingPaymentRequestController::class, 'get']);
    Route::post('/{masterId}/billing/payment_requests/pre_create_query', [MasterBillingPaymentRequestController::class, 'pre_create_query']);
    Route::post('/{masterId}/billing/payment_requests/create', [MasterBillingPaymentRequestController::class, 'create']);
    Route::get('/{masterId}/billing/payment_requests/calculations/{id}', [MasterBillingPaymentRequestController::class, 'view_calculations']);
    Route::get('/{masterId}/billing/payment_requests/invoice/{id}', [MasterBillingPaymentRequestController::class, 'get_invoice']);
    Route::get('/{masterId}/billing/payment_requests/files/{id}', [MasterBillingPaymentRequestController::class, 'get_files']);
    Route::put('/{masterId}/billing/payment_requests/approve/{id}', [MasterBillingPaymentRequestController::class, 'approve']);
    Route::post('/{masterId}/billing/payment_requests/master_approve/{id}', [MasterBillingPaymentRequestController::class, 'master_approve'])->middleware('ga');
    Route::put('/{masterId}/billing/payment_requests/reject/{id}', [MasterBillingPaymentRequestController::class, 'reject']);
    Route::post('/{masterId}/billing/payment_requests/fin_approve/{id}', [MasterBillingPaymentRequestController::class, 'fin_approve']); // TODO ->middleware('ga');
    Route::put('/{masterId}/billing/payment_requests/fin_reject/{id}', [MasterBillingPaymentRequestController::class, 'fin_reject']);
    Route::post('/{masterId}/billing/payment_requests/real_income/{id}', [MasterBillingPaymentRequestController::class, 'real_income'])->middleware('ga');

    Route::put('/{masterId}/billing/payment_requests/archive/{id}', [MasterBillingPaymentRequestController::class, 'archive']);

    Route::get('/{masterId}/billing/completed_transactions/all', [MasterBillingCompletedTransactionsController::class, 'index']);
    Route::post('/{masterId}/billing/completed_transactions/create', [MasterBillingCompletedTransactionsController::class, 'create']);

    Route::get('/{masterId}/billing/adjustments/all', [MasterBillingAdjustmentController::class, 'index']);
    Route::post('/{masterId}/billing/adjustments/create', [MasterBillingAdjustmentController::class, 'create'])->middleware('ga');
    Route::get('/{masterId}/billing/adjustments/{id}', [MasterBillingAdjustmentController::class, 'get']);
    Route::put('/{masterId}/billing/adjustments/update/{id}', [MasterBillingAdjustmentController::class, 'update'])->middleware('ga');
    Route::delete('/{masterId}/billing/adjustments/delete/{id}', [MasterBillingAdjustmentController::class, 'delete'])->middleware('ga');

    Route::get('/{masterId}/billing/chargeback/all', [MasterBillingChargebackController::class, 'index']);
    Route::post('/{masterId}/billing/chargeback/create', [MasterBillingChargebackController::class, 'create'])->middleware('ga');
    Route::get('/{masterId}/billing/chargeback/{id}', [MasterBillingChargebackController::class, 'get']);
    Route::post('/{masterId}/billing/chargeback/update/{id}', [MasterBillingChargebackController::class, 'update'])->middleware('ga');
    Route::delete('/{masterId}/billing/chargeback/delete/{id}', [MasterBillingChargebackController::class, 'delete'])->middleware('ga');

    Route::get('/{trafficEndpointId}/billing/sprav/payment_methods', [MasterBillingChargebackController::class, 'get_payment_methods']);
    Route::get('/{trafficEndpointId}/billing/sprav/payment_requests', [MasterBillingChargebackController::class, 'get_payment_requests']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'traffic_endpoint',
    // 'namespace' => 'Admin'
], function () {
    Route::get('/all', [TrafficEndpointController::class, 'index']);
    Route::post('/all', [TrafficEndpointController::class, 'post_index']);
    Route::post('/create', [TrafficEndpointController::class, 'create']);
    Route::get('/{id}', [TrafficEndpointController::class, 'get']);
    Route::put('/update/{id}', [TrafficEndpointController::class, 'update']);
    Route::patch('/archive/{id}', [TrafficEndpointController::class, 'archive']);
    Route::put('/reset_password/{id}', [TrafficEndpointController::class, 'reset_password']);
    Route::get('/{id}/logs', [TrafficEndpointController::class, 'log']);

    Route::put('/{id}/application/approve', [TrafficEndpointController::class, 'application_approve']);
    Route::put('/{id}/application/reject', [TrafficEndpointController::class, 'application_reject']);

    Route::get('/{id}/integrations', [TrafficEndpointController::class, 'get_integration']);
    Route::put('/update/{id}/integrations', [TrafficEndpointController::class, 'update_integration']);

    Route::post('/lead_analisis', [TrafficEndpointController::class, 'lead_analisis']);
    Route::post('/un_payable_leads', [TrafficEndpointController::class, 'un_payable_leads']);
    Route::post('/broker_simulator', [TrafficEndpointController::class, 'broker_simulator']);
    Route::post('/response_tools', [TrafficEndpointController::class, 'response_tools']);

    Route::get('/price/download', [TrafficEndpointController::class, 'download_price']);
    Route::get('/crgdeals/download', [TrafficEndpointController::class, 'download_crgdeals']);

    Route::get('/{trafficEndpointId}/offers/access', [TrafficEndpointController::class, 'offers_access_get']);
    Route::put('/{trafficEndpointId}/offers/update/access', [TrafficEndpointController::class, 'offers_access_update']);

    Route::get('/broker_simulator/group_by_fields', [TrafficEndpointController::class, 'broker_simulator_group_by_fields']);
    Route::get('/{trafficEndpointId}/feed_visualization_group_by_fields', [TrafficEndpointController::class, 'feed_visualization_group_by_fields']);
    Route::post('/{trafficEndpointId}/feed_visualization', [TrafficEndpointController::class, 'feed_visualization_get']);

    Route::get('/{trafficEndpointId}/payouts/all', [TrafficEndpointPayoutsController::class, 'index']);
    Route::post('/{trafficEndpointId}/payouts/create', [TrafficEndpointPayoutsController::class, 'create']);
    Route::get('/{trafficEndpointId}/payouts/{id}', [TrafficEndpointPayoutsController::class, 'get']);
    Route::get('/{trafficEndpointId}/payouts/logs/{id}', [TrafficEndpointPayoutsController::class, 'log']);
    Route::put('/{trafficEndpointId}/payouts/update/{id}', [TrafficEndpointPayoutsController::class, 'update']);
    Route::put('/{trafficEndpointId}/payouts/distributions_crg/{id}', [TrafficEndpointPayoutsController::class, 'distributions_crg']);
    Route::put('/{trafficEndpointId}/payouts/weekend_off_distributions_crg/{id}', [TrafficEndpointPayoutsController::class, 'weekend_off_distributions_crg']);
    Route::put('/{trafficEndpointId}/payouts/enable/{id}', [TrafficEndpointPayoutsController::class, 'enable']);
    Route::delete('/{trafficEndpointId}/payouts/delete/{id}', [TrafficEndpointPayoutsController::class, 'delete']);

    Route::get('/{trafficEndpointId}/dynamic_integration_ids', [TrafficEndpointDynamicIntegrationIDsController::class, 'index']);
    Route::post('/{trafficEndpointId}/dynamic_integration_ids/create', [TrafficEndpointDynamicIntegrationIDsController::class, 'create']);
    Route::get('/{trafficEndpointId}/dynamic_integration_ids/{id}', [TrafficEndpointDynamicIntegrationIDsController::class, 'get']);
    Route::put('/{trafficEndpointId}/dynamic_integration_ids/update/{id}', [TrafficEndpointDynamicIntegrationIDsController::class, 'update']);
    Route::delete('/{trafficEndpointId}/dynamic_integration_ids/delete/{id}', [TrafficEndpointDynamicIntegrationIDsController::class, 'delete']);

    Route::get('/{trafficEndpointId}/security/all', [TrafficEndpointSecurityController::class, 'index']);
    Route::post('/{trafficEndpointId}/security/create', [TrafficEndpointSecurityController::class, 'create']);
    Route::get('/{trafficEndpointId}/security/{id}', [TrafficEndpointSecurityController::class, 'get']);
    Route::put('/{trafficEndpointId}/security/update/{id}', [TrafficEndpointSecurityController::class, 'update']);
    Route::delete('/{trafficEndpointId}/security/delete/{id}', [TrafficEndpointSecurityController::class, 'delete']);

    Route::get('/{trafficEndpointId}/sub_publisher_tokens/all', [TrafficEndpointSubPublisherTokensController::class, 'index']);
    Route::post('/sprav/sub_publisher_tokens', [TrafficEndpointSubPublisherTokensController::class, 'index_sprav']);
    Route::post('/{trafficEndpointId}/sub_publisher_tokens/create', [TrafficEndpointSubPublisherTokensController::class, 'create']);
    Route::get('/{trafficEndpointId}/sub_publisher_tokens/{id}', [TrafficEndpointSubPublisherTokensController::class, 'get']);
    Route::put('/{trafficEndpointId}/sub_publisher_tokens/update/{id}', [TrafficEndpointSubPublisherTokensController::class, 'update']);
    Route::delete('/{trafficEndpointId}/sub_publisher_tokens/delete/{id}', [TrafficEndpointSubPublisherTokensController::class, 'delete']);

    Route::get('/{trafficEndpointId}/scrub/all', [TrafficEndpointScrubController::class, 'index']);
    Route::post('/{trafficEndpointId}/scrub/create', [TrafficEndpointScrubController::class, 'create']);
    Route::get('/{trafficEndpointId}/scrub/{id}', [TrafficEndpointScrubController::class, 'get']);
    Route::put('/{trafficEndpointId}/scrub/update/{id}', [TrafficEndpointScrubController::class, 'update']);
    Route::delete('/{trafficEndpointId}/scrub/delete/{id}', [TrafficEndpointScrubController::class, 'delete']);

    Route::get('/{trafficEndpointId}/private_deals/all', [TrafficEndpointPrivateDealsController::class, 'index']);
    Route::post('/{trafficEndpointId}/private_deals/create', [TrafficEndpointPrivateDealsController::class, 'create']);
    Route::get('/{trafficEndpointId}/private_deals/logs/{id}', [TrafficEndpointPrivateDealsController::class, 'logs']);
    Route::get('/{trafficEndpointId}/private_deals/{id}', [TrafficEndpointPrivateDealsController::class, 'get']);
    Route::put('/{trafficEndpointId}/private_deals/update/{id}', [TrafficEndpointPrivateDealsController::class, 'update']);
    Route::delete('/{trafficEndpointId}/private_deals/delete/{id}', [TrafficEndpointPrivateDealsController::class, 'delete']);

    Route::put('/{trafficEndpointId}/billing/manual_status', [TrafficEndpointBillingController::class, 'manual_status']);

    Route::get('/{trafficEndpointId}/billing/general_balances/feed', [TrafficEndpointBillingGeneralBalancesController::class, 'feed_billing_general_balances']);
    Route::get('/{trafficEndpointId}/billing/general_balances/balances_log', [TrafficEndpointBillingGeneralBalancesController::class, 'feed_billing_balances_log']);
    Route::post('/{trafficEndpointId}/billing/general_balances/balances_log', [TrafficEndpointBillingGeneralBalancesController::class, 'post_feed_billing_balances_log']);
    Route::post('/{trafficEndpointId}/billing/general_balances/history_log', [TrafficEndpointBillingGeneralBalancesController::class, 'history_log_billing_general_balances']);
    Route::put('/{trafficEndpointId}/billing/general_balances/balances_log/update/{logId}', [TrafficEndpointBillingGeneralBalancesController::class, 'update_billing_balances_log']);

    Route::get('/{trafficEndpointId}/billing/recalculate/logs', [TrafficEndpointBillingGeneralBalancesController::class, 'recalculate_logs']);
    Route::post('/{trafficEndpointId}/billing/recalculate/logs', [TrafficEndpointBillingGeneralBalancesController::class, 'post_recalculate_logs']);

    Route::get('/{trafficEndpointId}/billing/entities/all', [TrafficEndpointBillingEntitiesController::class, 'index']);
    Route::get('/{trafficEndpointId}/billing/entities/{entityId}', [TrafficEndpointBillingEntitiesController::class, 'get']);
    Route::post('/{trafficEndpointId}/billing/entities/create', [TrafficEndpointBillingEntitiesController::class, 'create'])->middleware('ga');
    Route::post('/{trafficEndpointId}/billing/entities/update/{entityId}', [TrafficEndpointBillingEntitiesController::class, 'update'])->middleware('ga');
    Route::delete('/{trafficEndpointId}/billing/entities/delete/{entityId}', [TrafficEndpointBillingEntitiesController::class, 'remove'])->middleware('ga');

    Route::get('/{trafficEndpointId}/billing/payment_methods/all', [TrafficEndpointBillingPaymentMethodsController::class, 'all']);
    Route::get('/{trafficEndpointId}/billing/payment_methods/{id}', [TrafficEndpointBillingPaymentMethodsController::class, 'get']);
    Route::post('/{trafficEndpointId}/billing/payment_methods/create', [TrafficEndpointBillingPaymentMethodsController::class, 'create']);
    Route::post('/{trafficEndpointId}/billing/payment_methods/update/{id}', [TrafficEndpointBillingPaymentMethodsController::class, 'update']);
    Route::put('/{trafficEndpointId}/billing/payment_methods/select/{paymentMethodId}', [TrafficEndpointBillingPaymentMethodsController::class, 'select']);
    Route::get('/{trafficEndpointId}/billing/payment_methods/files/{id}', [TrafficEndpointBillingPaymentMethodsController::class, 'files']);

    Route::get('/{trafficEndpointId}/billing/payment_requests/all', [TrafficEndpointBillingPaymentRequestsController::class, 'index']);
    Route::get('/{trafficEndpointId}/billing/payment_requests/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'get']);
    Route::get('/{trafficEndpointId}/billing/payment_requests/calculations/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'calculations']);
    Route::post('/{trafficEndpointId}/billing/payment_requests/crg_details/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'crg_details']);
    Route::post('/{trafficEndpointId}/billing/payment_requests/pre_create_query', [TrafficEndpointBillingPaymentRequestsController::class, 'pre_create_query']);
    Route::post('/{trafficEndpointId}/billing/payment_requests/create', [TrafficEndpointBillingPaymentRequestsController::class, 'create'])->middleware('ga');
    Route::get('/{trafficEndpointId}/billing/payment_requests/invoice/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'get_invoice']);
    Route::get('/{trafficEndpointId}/billing/payment_requests/files/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'files']);
    Route::post('/{trafficEndpointId}/billing/payment_requests/approve/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'approve']);
    Route::post('/{trafficEndpointId}/billing/payment_requests/reject/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'reject']);
    Route::post('/{trafficEndpointId}/billing/payment_requests/archive/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'archive']);
    Route::post('/{trafficEndpointId}/billing/payment_requests/master_approve/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'master_approve'])->middleware('ga');
    Route::post('/{trafficEndpointId}/billing/payment_requests/real_income/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'real_income'])->middleware('ga');
    Route::post('/{trafficEndpointId}/billing/payment_requests/final_approve/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'final_approve'])->middleware('ga');
    Route::put('/{trafficEndpointId}/billing/payment_requests/final_reject/{id}', [TrafficEndpointBillingPaymentRequestsController::class, 'final_reject']); //->middleware('ga');

    Route::get('/{brokerId}/billing/completed_transactions/all', [TrafficEndpointBillingCompletedTransactionsController::class, 'index']);

    Route::get('/{brtrafficEndpointIdokerId}/billing/adjustments/all', [TrafficEndpointBillingAdjustmentController::class, 'index']);
    Route::post('/{trafficEndpointId}/billing/adjustments/create', [TrafficEndpointBillingAdjustmentController::class, 'create'])->middleware('ga');
    Route::get('/{trafficEndpointId}/billing/adjustments/{id}', [TrafficEndpointBillingAdjustmentController::class, 'get']);
    Route::put('/{trafficEndpointId}/billing/adjustments/update/{id}', [TrafficEndpointBillingAdjustmentController::class, 'update'])->middleware('ga');
    Route::delete('/{trafficEndpointId}/billing/adjustments/delete/{id}', [TrafficEndpointBillingAdjustmentController::class, 'delete'])->middleware('ga');

    Route::get('/{trafficEndpointId}/billing/chargeback/all', [TrafficEndpointBillingChargebackController::class, 'index']);
    Route::post('/{trafficEndpointId}/billing/chargeback/create', [TrafficEndpointBillingChargebackController::class, 'create'])->middleware('ga');
    Route::get('/{trafficEndpointId}/billing/chargeback/{id}', [TrafficEndpointBillingChargebackController::class, 'get']);
    Route::put('/{trafficEndpointId}/billing/chargeback/update/{id}', [TrafficEndpointBillingChargebackController::class, 'update'])->middleware('ga');
    Route::delete('/{trafficEndpointId}/billing/chargeback/delete/{id}', [TrafficEndpointBillingChargebackController::class, 'delete'])->middleware('ga');

    Route::get('/{trafficEndpointId}/billing/sprav/payment_methods', [TrafficEndpointBillingController::class, 'get_payment_methods']);
    Route::get('/{trafficEndpointId}/billing/chargeback/sprav/payment_requests', [TrafficEndpointBillingChargebackController::class, 'get_payment_requests']);

});

Route::group([
    'middleware' => 'api',
    'prefix' => 'user',
    // 'namespace' => 'Admin'
], function () {
    Route::get('/all', [UserController::class, 'index']);
    Route::post('/create', [UserController::class, 'create']);
    Route::get('/{id}', [UserController::class, 'get']);
    Route::get('/{id}/permissions', [UserController::class, 'get_permissions']);
    Route::put('/{id}/permissions/update', [UserController::class, 'update_permissions']);
    Route::put('/update/{id}', [UserController::class, 'update']);
    Route::patch('/archive/{id}', [UserController::class, 'archive']);
    Route::put('/reset_password/{id}', [UserController::class, 'reset_password']);
});

Route::group(
    [
        'middleware' => 'api',
        'prefix' => 'settings'
    ],
    function () {
        Route::get('/', [SettingsController::class, 'get']);
        Route::put('/set', [SettingsController::class, 'set']);
        Route::get('/payment_methods', [SettingsController::class, 'payment_methods']);
        Route::post('/payment_methods/create', [SettingsController::class, 'create_payment_methods']);
        Route::get('/payment_companies', [SettingsController::class, 'payment_companies']);
        Route::post('/payment_companies/create', [SettingsController::class, 'create_payment_companies']);
        Route::get('/subscribers', [SettingsController::class, 'get_subscribers']);
        Route::post('/subscribers/update', [SettingsController::class, 'update_subscribers']);
    }
);

Route::group([
    'middleware' => 'api',
    'prefix' => 'test'
], function () {
    Route::get('/all', [TestController::class, 'index']);
    Route::post('/create', [TestController::class, 'create']);
    Route::put('/update/{id}', [TestController::class, 'update']);
    Route::delete('/delete/{id}', [TestController::class, 'delete']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'planning'
], function () {
    Route::post('/', [PlanningController::class, 'run']);
    Route::get('/countries_and_languages', [PlanningController::class, 'countries_and_languages']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'storage'
], function () {
    Route::get('/{fileId}', [StorageController::class, 'get']);
    Route::get('/download/{fileId}', [StorageController::class, 'download']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'marketing_suite'
], function () {
    Route::get('/all', [MarketingSuiteController::class, 'index']);
    Route::get('/{id}', [MarketingSuiteController::class, 'get']);
    Route::get('/get_tracking_link/{id}', [MarketingSuiteController::class, 'get_tracking_link']);
    Route::post('/create', [MarketingSuiteController::class, 'create']);
    Route::post('/update/{id}', [MarketingSuiteController::class, 'update']);
    Route::delete('/delete/{id}', [MarketingSuiteController::class, 'delete']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'gravity'
], function () {
    Route::get('/leads/{type}', [GravityController::class, 'run']);
    Route::get('/leads/title/{type}', [GravityController::class, 'run_title']);
    Route::get('/log', [GravityController::class, 'logs']);
    Route::post('/post_log', [GravityController::class, 'post_logs']);
    Route::get('/reject/{id}', [GravityController::class, 'reject']);
    Route::get('/approve/{id}', [GravityController::class, 'approve']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'crm'
], function () {
    Route::post('/ftd', [CRMController::class, 'ftd']);
    Route::post('/leads', [CRMController::class, 'leads']);
    Route::post('/mismatch', [CRMController::class, 'mismatch']);
    Route::get('/status_lead_history/{id}', [CRMController::class, 'status_lead_history']);
    Route::post('/resync/get', [CRMController::class, 'get_resync']);
    Route::post('/resync', [CRMController::class, 'resync']);
    Route::post('/download_recalculation_changes_log', [CRMController::class, 'download_recalculation_changes_log']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'support'
], function () {
    Route::get('/all', [SupportController::class, 'index']);
    Route::post('/page/{page}', [SupportController::class, 'page']);
    Route::get('/{id}', [SupportController::class, 'get']);
    Route::post('/create', [SupportController::class, 'create']);
    Route::post('/update/{id}', [SupportController::class, 'update']);
    Route::delete('/delete/{id}', [SupportController::class, 'delete']);
    Route::post('/{id}/send_comment', [SupportController::class, 'send_comment']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'clients'
], function () {
    Route::get('/', [ClientController::class, 'index']);
    Route::post('/page/{page}', [ClientController::class, 'page_index']);
    Route::get('/{clientId}', [ClientController::class, 'get']);
    Route::post('/create', [ClientController::class, 'create']);
    Route::post('/update/{clientId}', [ClientController::class, 'update']);
    Route::patch('/archive/{clientId}', [ClientController::class, 'draft']);
    Route::delete('/delete/{clientId}', [ClientController::class, 'delete']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'tag_management'
], function () {
    Route::get('/', [TagManagementController::class, 'index']);
    Route::get('/{clientId}', [TagManagementController::class, 'get']);
    Route::post('/create', [TagManagementController::class, 'create']);
    Route::post('/update/{clientId}', [TagManagementController::class, 'update']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'fireftd'
], function () {
    Route::post('/', [FireFTDController::class, 'run']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'emailschecker'
], function () {
    Route::post('/', [EmailsCheckerController::class, 'run']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'quality_report'
], function () {
    Route::post('/', [QualityReportController::class, 'run']);
    Route::post('/download', [QualityReportController::class, 'download']);
    Route::get('/pivot', [QualityReportController::class, 'pivot']);
    Route::get('/metrics', [QualityReportController::class, 'metrics']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'report'
], function () {
    Route::post('/', [ReportController::class, 'run']);
    Route::post('/download', [ReportController::class, 'download']);
    Route::get('/pivot', [ReportController::class, 'pivot']);
    Route::get('/metrics', [ReportController::class, 'metrics']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'click_report'
], function () {
    Route::post('/', [ClickReportController::class, 'run']);
    Route::post('/download', [ClickReportController::class, 'download']);
    Route::get('/pivot', [ClickReportController::class, 'pivot']);
    Route::get('/metrics', [ClickReportController::class, 'metrics']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'billings'
], function () {
    Route::get('/overall', [BillingsController::class, 'overall']);
    Route::get('/pending_payments', [BillingsController::class, 'pending_payments']);
    Route::post('/brokers_balances', [BillingsController::class, 'brokers_balances']);
    Route::post('/endpoint_balances', [BillingsController::class, 'endpoint_balances']);
    Route::post('/approved ', [BillingsController::class, 'approved']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'marketing_billings'
], function () {
    Route::get('/overall', [MarketingBillingsController::class, 'overall']);
    Route::get('/pending_payments', [MarketingBillingsController::class, 'pending_payments']);
    Route::post('/advertisers_balances', [MarketingBillingsController::class, 'advertisers_balances']);
    Route::post('/affiliates_balances', [MarketingBillingsController::class, 'affiliates_balances']);
    Route::post('/approved ', [MarketingBillingsController::class, 'approved']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'performance'
], function () {
    Route::post('/general', [PerformanceController::class, 'general']);
    Route::post('/traffic_endpoints', [PerformanceController::class, 'traffic_endpoints']);
    Route::post('/brokers', [PerformanceController::class, 'brokers']);
    Route::post('/vendors', [PerformanceController::class, 'vendors']);
    Route::post('/deep_dive', [PerformanceController::class, 'deep_dive']);
    Route::post('/download', [PerformanceController::class, 'download']);

    Route::get('/settings/broker_statuses/all', [PerformanceSettingsController::class, 'all']);
    Route::get('/settings/broker_statuses/get/{id}', [PerformanceSettingsController::class, 'get']);
    Route::post('/settings/broker_statuses/create', [PerformanceSettingsController::class, 'create']);
    Route::put('/settings/broker_statuses/update/{id}', [PerformanceSettingsController::class, 'update']);
    Route::delete('/settings/broker_statuses/delete/{id}', [PerformanceSettingsController::class, 'delete']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'dashboard'
], function () {
    Route::get('/', [DashboardController::class, 'index']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'investigate'
], function () {
    Route::get('/user/{id}', [InvestigateController::class, 'logs']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'marketing_investigate'
], function () {
    Route::get('/{event}/{id}', [MarketingInvestigateController::class, 'logs']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'leads'
], function () {
    Route::put('/test_lead/{leadId}', [LeadsController::class, 'test_lead']);
    Route::post('/alerts/{leadId}', [LeadsController::class, 'createAlerts']);
    Route::delete('/alerts/{alertID}', [LeadsController::class, 'deleteAlerts']);
    Route::get('/alerts', [LeadsController::class, 'listAlerts']);
    Route::put('/approve/{leadId}', [LeadsController::class, 'approve']);
    Route::put('/fire_ftd/{leadId}', [LeadsController::class, 'fire_ftd'])->middleware('ga');
    Route::get('/{leadId}/crg_lead', [LeadsController::class, 'crg_lead']);
    Route::post('/mark_crg_lead/{leadId}', [LeadsController::class, 'mark_crg_lead']);
    Route::get('/{leadId}/crg_ftd', [LeadsController::class, 'crg_ftd']);
    Route::post('/change_crg_ftd/{leadId}', [LeadsController::class, 'change_crg_ftd']);
    Route::get('/{leadId}/payout', [LeadsController::class, 'get_payout']);
    Route::post('/update/{leadId}/payout', [LeadsController::class, 'update_payout']);
    Route::post('/send_test_lead/data', [LeadsController::class, 'test_lead_data']);
    Route::post('/send_test_lead/send', [LeadsController::class, 'test_lead_send']);
    Route::get('/{leadId}/change_payout_cpl_lead', [LeadsController::class, 'get_change_payout_cpl_lead']);
    Route::post('/{leadId}/change_payout_cpl_lead', [LeadsController::class, 'post_change_payout_cpl_lead']);
});


Route::group([
    'middleware' => 'api',
    'prefix' => 'logs'
], function () {
    Route::get('/{page}', [LogsController::class, 'logs']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'notifications'
], function () {
    Route::get('/', [NotificationsController::class, 'notifications']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'leads_review'
], function () {
    Route::post('/', [LeadsReviewController::class, 'index']);
    Route::put('/checked/{leadId}', [LeadsReviewController::class, 'checked']);

    Route::get('/tickets', [LeadsReviewController::class, 'index_ticket']);
    Route::post('/tickets/page/{page}', [LeadsReviewController::class, 'page_ticket']);
    Route::get('/tickets/{ticketId}', [LeadsReviewController::class, 'get_ticket']);
    Route::post('/tickets/{ticketId}', [LeadsReviewController::class, 'get_ticket']);
    Route::post('/tickets/create/{leadId}', [LeadsReviewController::class, 'create_ticket']);
    Route::post('/tickets/update/{ticketId}', [LeadsReviewController::class, 'update_ticket']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'advertisers'
], function () {
    Route::get('/', [AdvertisersController::class, 'index']);
    Route::post('/page/{page}', [AdvertisersController::class, 'page_index']);
    Route::get('/{advertiserId}', [AdvertisersController::class, 'get']);
    Route::post('/create', [AdvertisersController::class, 'create']);
    Route::put('/update/{advertiserId}', [AdvertisersController::class, 'update']);
    Route::patch('/draft/{advertiserId}', [AdvertisersController::class, 'draft']);
    Route::delete('/delete/{advertiserId}', [AdvertisersController::class, 'delete']);
    Route::post('/email/{affiliateId}', [AdvertisersController::class, 'get_email'])->middleware('ga');
    Route::post('/tracking_link/{affiliateId}', [AdvertisersController::class, 'tracking_link']);

    Route::post('/un_payable', [AdvertisersController::class, 'un_payable']);

    Route::get('/{advertiserId}/campaigns', [CampaignsController::class, 'index']);
    Route::post('/{advertiserId}/campaigns/page/{page}', [CampaignsController::class, 'page_index']);
    Route::get('/{advertiserId}/campaigns/{campaignId}', [CampaignsController::class, 'get']);
    Route::post('/{advertiserId}/campaigns/create', [CampaignsController::class, 'create']);
    Route::post('/{advertiserId}/campaigns/update/{campaignId}', [CampaignsController::class, 'update']);
    Route::patch('/{advertiserId}/campaigns/draft/{campaignId}', [CampaignsController::class, 'draft']);

    Route::get('/{advertiserId}/campaigns/tags/{campaignId}', [CampaignsController::class, 'get_tags']);
    Route::put('/{advertiserId}/campaigns/tags/update/{campaignId}', [CampaignsController::class, 'set_tags']);

    Route::put('/{advertiserId}/campaigns/general_payout/update/{campaignId}', [CampaignsController::class, 'general_payout_update']);
    Route::put('/{advertiserId}/campaigns/budget/update/{campaignId}', [CampaignsController::class, 'budget_update']);

    Route::get('/{advertiserId}/campaigns/{campaignId}/payouts', [CampaignsPayoutsController::class, 'index']);
    Route::post('/{advertiserId}/campaigns/{campaignId}/payouts/create', [CampaignsPayoutsController::class, 'create']);
    Route::get('/{advertiserId}/campaigns/{campaignId}/payouts/{id}', [CampaignsPayoutsController::class, 'get']);
    Route::get('/{advertiserId}/campaigns/{campaignId}/payouts/logs/{id}', [CampaignsPayoutsController::class, 'log']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/payouts/enable/{id}', [CampaignsPayoutsController::class, 'enable']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/payouts/update/{id}', [CampaignsPayoutsController::class, 'update']);
    Route::delete('/{advertiserId}/campaigns/{campaignId}/payouts/delete/{id}', [CampaignsPayoutsController::class, 'delete']);

    Route::get('/{advertiserId}/campaigns/{campaignId}/private_deals', [CampaignsPrivateDealsController::class, 'index']);
    Route::post('/{advertiserId}/campaigns/{campaignId}/private_deals/create', [CampaignsPrivateDealsController::class, 'create']);
    Route::get('/{advertiserId}/campaigns/{campaignId}/private_deals/{id}', [CampaignsPrivateDealsController::class, 'get']);
    Route::get('/{advertiserId}/campaigns/{campaignId}/private_deals/logs/{id}', [CampaignsPrivateDealsController::class, 'log']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/private_deals/enable/{id}', [CampaignsPrivateDealsController::class, 'enable']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/private_deals/update/{id}', [CampaignsPrivateDealsController::class, 'update']);
    Route::delete('/{advertiserId}/campaigns/{campaignId}/private_deals/delete/{id}', [CampaignsPrivateDealsController::class, 'delete']);

    Route::get('/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocations', [CampaignsBudgetController::class, 'endpoint_allocations']);
    Route::post('/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocation/create', [CampaignsBudgetController::class, 'endpoint_allocation_create']);
    Route::get('/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocation/{id}', [CampaignsBudgetController::class, 'endpoint_allocation_get']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocation/update/{id}', [CampaignsBudgetController::class, 'endpoint_allocation_update']);
    Route::delete('/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocation/delete/{id}', [CampaignsBudgetController::class, 'endpoint_allocation_delete']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocation/enable/{allocationId}', [CampaignsBudgetController::class, 'endpoint_allocation_enable']);

    Route::put('/{advertiserId}/campaigns/{campaignId}/limitation/update', [CampaignsController::class, 'limitation_update']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/limitation/force_sub_publisher/update', [CampaignsController::class, 'limitation_force_sub_publisher_update']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/endpoint_managment/update', [CampaignsController::class, 'endpoint_managment_update']);

    Route::get('/{advertiserId}/campaigns/{campaignId}/targeting_locations', [CampaignsTargetingLocationsController::class, 'index']);
    Route::post('/{advertiserId}/campaigns/{campaignId}/targeting_locations/create', [CampaignsTargetingLocationsController::class, 'create']);
    Route::get('/{advertiserId}/campaigns/{campaignId}/targeting_locations/{id}', [CampaignsTargetingLocationsController::class, 'get']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/targeting_locations/enable/{id}', [CampaignsTargetingLocationsController::class, 'enable']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/targeting_locations/update/{id}', [CampaignsTargetingLocationsController::class, 'update']);
    Route::delete('/{advertiserId}/campaigns/{campaignId}/targeting_locations/delete/{id}', [CampaignsTargetingLocationsController::class, 'delete']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/targeting/world_wide/update', [CampaignsController::class, 'targeting_world_wide_update']);

    Route::get('/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers', [CampaignsLimitationController::class, 'sub_publishers']);
    Route::post('/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/create', [CampaignsLimitationController::class, 'sub_publishers_create']);
    Route::get('/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/{id}', [CampaignsLimitationController::class, 'sub_publishers_get']);
    Route::put('/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/update/{id}', [CampaignsLimitationController::class, 'sub_publishers_update']);
    Route::delete('/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/delete/{id}', [CampaignsLimitationController::class, 'sub_publishers_delete']);

    Route::get('/{advertiserId}/post_events', [AdvertisersPostEventsController::class, 'index']);
    Route::post('/{advertiserId}/post_events/create', [AdvertisersPostEventsController::class, 'create']);
    Route::get('/{advertiserId}/post_events/{id}', [AdvertisersPostEventsController::class, 'get']);
    Route::put('/{advertiserId}/post_events/update/{id}', [AdvertisersPostEventsController::class, 'update']);
    Route::delete('/{advertiserId}/post_events/delete/{id}', [AdvertisersPostEventsController::class, 'delete']);

    Route::get('/{advertiserId}/billing/general/balance', [AdvertisersBillingController::class, 'general_balance']);
    Route::get('/{advertiserId}/billing/general/balance/logs', [AdvertisersBillingController::class, 'general_balance_logs']);
    Route::post('/{advertiserId}/billing/general/balance/logs', [AdvertisersBillingController::class, 'post_general_balance_logs']);

    Route::put('/{advertiserId}/billing/general/balance/logs/update/{logId}', [AdvertisersBillingController::class, 'update_general_balance_logs']);
    Route::put('/{advertiserId}/billing/general/settings/negative_balance', [AdvertisersBillingController::class, 'settings_negative_balance'])->middleware('ga');
    Route::put('/{advertiserId}/billing/general/settings/credit_amount', [AdvertisersBillingController::class, 'settings_credit_amount'])->middleware('ga');
    Route::get('/{advertiserId}/billing/general/logs', [AdvertisersBillingController::class, 'logs']);
    Route::post('/{advertiserId}/billing/general/logs', [AdvertisersBillingController::class, 'logs']);
    Route::put('/{advertiserId}/billing/manual_status', [AdvertisersBillingController::class, 'manual_status']);

    Route::get('/{advertiserId}/billing/entities/all', [AdvertisersBillingEntitiesController::class, 'index']);
    Route::post('/{advertiserId}/billing/entities/create', [AdvertisersBillingEntitiesController::class, 'create'])->middleware('ga');
    Route::get('/{advertiserId}/billing/entities/{id}', [AdvertisersBillingEntitiesController::class, 'get']);
    Route::post('/{advertiserId}/billing/entities/update/{id}', [AdvertisersBillingEntitiesController::class, 'update'])->middleware('ga');
    Route::delete('/{advertiserId}/billing/entities/delete/{id}', [AdvertisersBillingEntitiesController::class, 'delete'])->middleware('ga');

    Route::get('/{advertiserId}/billing/payment_methods/all', [AdvertisersBillingPaymentMethodController::class, 'index']);
    Route::put('/{advertiserId}/billing/payment_methods/select/{id}', [AdvertisersBillingPaymentMethodController::class, 'select']);

    Route::get('/{advertiserId}/billing/payment_requests/all', [AdvertisersBillingPaymentRequestController::class, 'index']);
    Route::get('/{advertiserId}/billing/payment_requests/completed', [AdvertisersBillingPaymentRequestController::class, 'completed']);
    Route::get('/{advertiserId}/billing/payment_requests/{id}', [AdvertisersBillingPaymentRequestController::class, 'get']);
    Route::post('/{advertiserId}/billing/payment_requests/pre_create_query', [AdvertisersBillingPaymentRequestController::class, 'pre_create_query']);
    Route::post('/{advertiserId}/billing/payment_requests/create', [AdvertisersBillingPaymentRequestController::class, 'create']);
    Route::get('/{advertiserId}/billing/payment_requests/calculations/{id}', [AdvertisersBillingPaymentRequestController::class, 'view_calculations']);
    Route::get('/{advertiserId}/billing/payment_requests/invoice/{id}', [AdvertisersBillingPaymentRequestController::class, 'get_invoice']);
    Route::get('/{advertiserId}/billing/payment_requests/files/{id}', [AdvertisersBillingPaymentRequestController::class, 'get_files']);
    Route::put('/{advertiserId}/billing/payment_requests/approve/{id}', [AdvertisersBillingPaymentRequestController::class, 'approve']);
    Route::put('/{advertiserId}/billing/payment_requests/change/{id}', [AdvertisersBillingPaymentRequestController::class, 'change']);
    Route::put('/{advertiserId}/billing/payment_requests/reject/{id}', [AdvertisersBillingPaymentRequestController::class, 'reject']);
    Route::post('/{advertiserId}/billing/payment_requests/fin_approve/{id}', [AdvertisersBillingPaymentRequestController::class, 'fin_approve']); // TODO ->middleware('ga');
    Route::put('/{advertiserId}/billing/payment_requests/fin_reject/{id}', [AdvertisersBillingPaymentRequestController::class, 'fin_reject']);
    Route::put('/{advertiserId}/billing/payment_requests/real_income/{id}', [AdvertisersBillingPaymentRequestController::class, 'real_income']);
    Route::put('/{advertiserId}/billing/payment_requests/archive/{id}', [AdvertisersBillingPaymentRequestController::class, 'archive']);

    Route::get('/{advertiserId}/billing/completed_transactions/all', [AdvertisersBillingCompletedTransactionsController::class, 'index']);
    Route::post('/{advertiserId}/billing/completed_transactions/create', [AdvertisersBillingCompletedTransactionsController::class, 'create']);

    Route::get('/{advertiserId}/billing/adjustments/all', [AdvertisersBillingAdjustmentController::class, 'index']);
    Route::post('/{advertiserId}/billing/adjustments/create', [AdvertisersBillingAdjustmentController::class, 'create'])->middleware('ga');
    Route::get('/{advertiserId}/billing/adjustments/{id}', [AdvertisersBillingAdjustmentController::class, 'get']);
    Route::put('/{advertiserId}/billing/adjustments/update/{id}', [AdvertisersBillingAdjustmentController::class, 'update'])->middleware('ga');
    Route::delete('/{advertiserId}/billing/adjustments/delete/{id}', [AdvertisersBillingAdjustmentController::class, 'delete'])->middleware('ga');

    Route::get('/{advertiserId}/billing/chargeback/all', [AdvertisersBillingChargebackController::class, 'index']);
    Route::post('/{advertiserId}/billing/chargeback/create', [AdvertisersBillingChargebackController::class, 'create'])->middleware('ga');
    Route::get('/{advertiserId}/billing/chargeback/{id}', [AdvertisersBillingChargebackController::class, 'get']);
    Route::post('/{advertiserId}/billing/chargeback/update/{id}', [AdvertisersBillingChargebackController::class, 'update'])->middleware('ga');
    Route::delete('/{advertiserId}/billing/chargeback/delete/{id}', [AdvertisersBillingChargebackController::class, 'delete'])->middleware('ga');
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'affiliates'
], function () {
    Route::get('/', [AffiliatesController::class, 'index']);
    Route::post('/', [AffiliatesController::class, 'post_index']);
    Route::post('/page/{page}', [AffiliatesController::class, 'page_index']);
    Route::get('/{affiliateId}', [AffiliatesController::class, 'get']);
    Route::post('/email/{affiliateId}', [AffiliatesController::class, 'get_email'])->middleware('ga');
    Route::post('/create', [AffiliatesController::class, 'create']);
    Route::put('/update/{affiliateId}', [AffiliatesController::class, 'update']);
    Route::put('/credentials/{affiliateId}', [AffiliatesController::class, 'update_credentials']);
    Route::put('/postbacks/{affiliateId}', [AffiliatesController::class, 'update_postbacks']);
    Route::patch('/draft/{affiliateId}', [AffiliatesController::class, 'draft']);
    Route::delete('/delete/{affiliateId}', [AffiliatesController::class, 'delete']);
    Route::put('/reset_password/{affiliateId}', [AffiliatesController::class, 'reset_password']);

    Route::get('/sprav/offers', [AffiliatesController::class, 'sprav_offers']);

    Route::post('/un_payable', [AffiliatesController::class, 'un_payable']);
    Route::put('/{affiliateId}/application/approve', [AffiliatesController::class, 'application_approve']);
    Route::put('/{affiliateId}/application/reject', [AffiliatesController::class, 'application_reject']);

    Route::get('/allow_categories/{affiliateId}', [AffiliatesController::class, 'allow_categories']);
    Route::post('/tracking_link/{affiliateId}', [AffiliatesController::class, 'tracking_link']);

    Route::put('/{affiliateId}/billing/manual_status', [AffiliateBillingController::class, 'manual_status']);

    Route::get('/{affiliateId}/billing/general_balances/feed', [AffiliateBillingGeneralBalancesController::class, 'feed_billing_general_balances']);
    Route::get('/{affiliateId}/billing/general_balances/balances_log', [AffiliateBillingGeneralBalancesController::class, 'feed_billing_balances_log']);
    Route::post('/{affiliateId}/billing/general_balances/balances_log', [AffiliateBillingGeneralBalancesController::class, 'post_feed_billing_balances_log']);
    Route::post('/{affiliateId}/billing/general_balances/history_log', [AffiliateBillingGeneralBalancesController::class, 'history_log_billing_general_balances']);
    Route::put('/{affiliateId}/billing/general_balances/balances_log/update/{logId}', [AffiliateBillingGeneralBalancesController::class, 'update_billing_balances_log']);

    Route::get('/{affiliateId}/billing/entities/all', [AffiliateBillingEntitiesController::class, 'index']);
    Route::get('/{affiliateId}/billing/entities/{entityId}', [AffiliateBillingEntitiesController::class, 'get']);
    Route::post('/{affiliateId}/billing/entities/create', [AffiliateBillingEntitiesController::class, 'create'])->middleware('ga');
    Route::post('/{affiliateId}/billing/entities/update/{entityId}', [AffiliateBillingEntitiesController::class, 'update'])->middleware('ga');
    Route::delete('/{affiliateId}/billing/entities/delete/{entityId}', [AffiliateBillingEntitiesController::class, 'remove'])->middleware('ga');

    Route::get('/{affiliateId}/billing/payment_methods/all', [AffiliateBillingPaymentMethodsController::class, 'all']);
    Route::post('/{affiliateId}/billing/payment_methods/create', [AffiliateBillingPaymentMethodsController::class, 'create']);
    Route::put('/{affiliateId}/billing/payment_methods/select/{paymentMethodId}', [AffiliateBillingPaymentMethodsController::class, 'select']);

    Route::get('/{affiliateId}/billing/payment_requests/all', [AffiliateBillingPaymentRequestsController::class, 'index']);
    Route::get('/{affiliateId}/billing/payment_requests/{id}', [AffiliateBillingPaymentRequestsController::class, 'get']);
    Route::get('/{affiliateId}/billing/payment_requests/calculations/{id}', [AffiliateBillingPaymentRequestsController::class, 'calculations']);
    Route::post('/{affiliateId}/billing/payment_requests/pre_create_query', [AffiliateBillingPaymentRequestsController::class, 'pre_create_query']);
    Route::post('/{affiliateId}/billing/payment_requests/create', [AffiliateBillingPaymentRequestsController::class, 'create']);
    Route::get('/{affiliateId}/billing/payment_requests/invoice/{id}', [AffiliateBillingPaymentRequestsController::class, 'get_invoice']);
    Route::get('/{affiliateId}/billing/payment_requests/files/{id}', [AffiliateBillingPaymentRequestsController::class, 'files']);
    Route::post('/{affiliateId}/billing/payment_requests/approve/{id}', [AffiliateBillingPaymentRequestsController::class, 'approve']);
    Route::post('/{affiliateId}/billing/payment_requests/reject/{id}', [AffiliateBillingPaymentRequestsController::class, 'reject']);
    Route::post('/{affiliateId}/billing/payment_requests/archive/{id}', [AffiliateBillingPaymentRequestsController::class, 'archive']);
    Route::post('/{affiliateId}/billing/payment_requests/master_approve/{id}', [AffiliateBillingPaymentRequestsController::class, 'master_approve'])->middleware('ga');
    Route::post('/{affiliateId}/billing/payment_requests/real_income/{id}', [AffiliateBillingPaymentRequestsController::class, 'real_income'])->middleware('ga');
    Route::post('/{affiliateId}/billing/payment_requests/final_approve/{id}', [AffiliateBillingPaymentRequestsController::class, 'final_approve'])->middleware('ga');
    Route::put('/{affiliateId}/billing/payment_requests/final_reject/{id}', [AffiliateBillingPaymentRequestsController::class, 'final_reject']); //->middleware('ga');

    Route::get('/{affiliateId}/billing/completed_transactions/all', [AffiliateBillingCompletedTransactionsController::class, 'index']);

    Route::get('/{affiliateId}/billing/adjustments/all', [AffiliateBillingAdjustmentController::class, 'index']);
    Route::post('/{affiliateId}/billing/adjustments/create', [AffiliateBillingAdjustmentController::class, 'create'])->middleware('ga');
    Route::get('/{affiliateId}/billing/adjustments/{id}', [AffiliateBillingAdjustmentController::class, 'get']);
    Route::put('/{affiliateId}/billing/adjustments/update/{id}', [AffiliateBillingAdjustmentController::class, 'update'])->middleware('ga');
    Route::delete('/{affiliateId}/billing/adjustments/delete/{id}', [AffiliateBillingAdjustmentController::class, 'delete'])->middleware('ga');

    Route::get('/{affiliateId}/billing/chargeback/all', [AffiliateBillingChargebackController::class, 'index']);
    Route::post('/{affiliateId}/billing/chargeback/create', [AffiliateBillingChargebackController::class, 'create'])->middleware('ga');
    Route::get('/{affiliateId}/billing/chargeback/{id}', [AffiliateBillingChargebackController::class, 'get']);
    Route::put('/{affiliateId}/billing/chargeback/update/{id}', [AffiliateBillingChargebackController::class, 'update'])->middleware('ga');
    Route::delete('/{affiliateId}/billing/chargeback/delete/{id}', [AffiliateBillingChargebackController::class, 'delete'])->middleware('ga');

    Route::get('/{affiliateId}/billing/sprav/payment_methods', [AffiliateBillingController::class, 'get_payment_methods']);
    Route::get('/{affiliateId}/billing/chargeback/sprav/payment_requests', [AffiliateBillingChargebackController::class, 'get_payment_requests']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'marketing_gravity'
], function () {
    Route::get('/leads/{type}', [MarketingGravityController::class, 'run']);
    Route::get('/leads/title/{type}', [MarketingGravityController::class, 'run_title']);
    Route::get('/log', [MarketingGravityController::class, 'logs']);
    Route::get('/reject/{id}', [MarketingGravityController::class, 'reject']);
    Route::get('/approve/{id}', [MarketingGravityController::class, 'approve']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'marketing_report'
], function () {
    Route::post('/', [MarketingReportController::class, 'run']);
    Route::post('/download', [MarketingReportController::class, 'download']);
    Route::get('/pivot', [MarketingReportController::class, 'pivot']);
    Route::get('/metrics', [MarketingReportController::class, 'metrics']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'resync_report'
], function () {
    Route::post('/', [ResyncReportController::class, 'resync_report']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'validate'
], function () {
    Route::get('/cryptocurrency-address/{iso_code}/{wallet_address}', [ValidateController::class, 'cryptocurrency_address']);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'utils'
], function () {
    Route::post('/decrypt', [UtilsController::class, 'decrypt']);
});
