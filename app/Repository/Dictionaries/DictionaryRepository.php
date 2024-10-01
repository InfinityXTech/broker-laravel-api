<?php

namespace App\Repository\Dictionaries;

use App\Models\User;
use App\Models\Leads;
use App\Models\Broker;
use App\Models\Master;

use App\Scopes\ClientScope;
use App\Models\Integrations;
use App\Helpers\BucketHelper;
use App\Helpers\ClientHelper;
use App\Models\TagManagement;
use App\Helpers\GeneralHelper;
use App\Helpers\CurrencyHelper;
use App\Models\TrafficEndpoint;
use App\Models\MarketingCampaign;
use App\Models\MarketingAffiliate;
use App\Repository\BaseRepository;
use App\Models\MarketingAdvertiser;
use App\Models\Brokers\BrokerStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use App\Models\Brokers\BrokerIntegration;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Billings\BillingPaymentCompany;
use App\Repository\Dictionaries\IDictionaryRepository;
use App\Models\Advertisers\MarketingAdvertiserPostEvent;

class DictionaryRepository extends BaseRepository implements IDictionaryRepository
{

    private $cache_key;
    private $cache_ttl = 60;

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct()
    {
        $this->cache_key = 'dictionary_' . ClientHelper::clientId() . '_' . Auth::id();
    }

    public function brokers(string $status = ''): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $brokers = Cache::get($cache_key);
        if (isset($brokers)) {
            return $brokers;
        }

        if (!Gate::allows('brokers[active=1]')) {
            return [];
        }

        $query = Broker::query();

        if (!empty($status)) {
            $query = $query->where('status', '=', $status);
        }

        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $query = $query->where(function ($q) {
                $current_user_id = Auth::id();
                $q->orWhere('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id);
            });
        }

        $items = $query->get(['_id', 'partner_name', 'token', 'created_by', 'account_manager'])->map(function ($item) {
            $name = GeneralHelper::broker_name($item);
            return ['key' => $item->_id, 'value' => $name];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function tags(array $permissions = []): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $tags = Cache::get($cache_key);
        if (isset($tags)) {
            return $tags;
        }

        if (!ClientHelper::is_public_features('PT100')) {
            return [];
        }

        $query = TagManagement::query();
        if (!empty($permissions)) {
            $query->whereIn('permission', $permissions);
        }

        $tags = $query->get(['_id', 'name', 'status'])->map(function ($item) {
            return ['key' => $item->_id, 'value' => $item->name, 'active' => $item->status];
        })->toArray();

        Cache::put($cache_key, $tags, $this->cache_ttl);

        return $tags;
    }

    public function brokerTags(): array
    {
        return $this->tags(["0", "1"]);
    }

    public function endpointTags(): array
    {
        return $this->tags(["0", "2"]);
    }

    public function broker_integrations(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $broker_integrations = Cache::get($cache_key);
        if (isset($broker_integrations)) {
            return $broker_integrations;
        }

        if (!Gate::allows('brokers[active=1]')) {
            return [];
        }

        $query = BrokerIntegration::query();

        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $brokers = $this->brokers();
            $brokerIds = array_column($brokers, 'key');
            if (empty($brokerIds)) {
                $brokerIds[] = 'anything';
            }
            if (is_array($brokerIds)) {
                $query->whereIn('partnerId', $brokerIds);
            }
        }

        $items = $query->get(['_id', 'partnerId', 'name'])->map(function ($item) {
            return ['key' => $item->_id, 'brokerId' => $item->partnerId, 'value' => GeneralHelper::broker_integration_name(['partnerId' => $item->partnerId, 'name' => $item->name])];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function brokers_and_integrations(string $status = '1'): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $broker_integrations = Cache::get($cache_key);
        if (isset($broker_integrations)) {
            return $broker_integrations;
        }

        $brokers = $this->brokers($status);
        $broker_integrations = $this->broker_integrations();

        $integrations = [];

        foreach ($brokers as $broker) {
            foreach ($broker_integrations as $broker_integration) {
                if ($broker['key'] == $broker_integration['brokerId']) {
                    $integrations[] = ['key' => $broker_integration['key'], 'value' =>  $broker['value'] . ' - ' . $broker_integration['value']];
                }
            }
        }

        Cache::put($cache_key, $integrations, $this->cache_ttl);

        return $integrations;
    }

    public function all_brokers_and_integrations(): array
    {
        return $this->brokers_and_integrations('');
    }

    public function traffic_endpoints(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $traffic_endpoints = Cache::get($cache_key);
        if (isset($traffic_endpoints)) {
            return $traffic_endpoints;
        }

        if (!Gate::allows('traffic_endpoint[active=1]')) {
            return [];
        }

        $query = TrafficEndpoint::query();

        $query->where(function ($q) {
            $q->where('UnderReview', '=', 1)->orWhere('UnderReview', '=', null)->orWhereNull('UnderReview');;
        });

        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            $query = $query->where(function ($q) {
                $user_token = Auth::id();
                $q
                    ->orWhere('created_by', '=', $user_token)
                    ->orWhere('user_id', '=', $user_token)
                    ->orWhere('account_manager', '=', $user_token);
            });
        }

        if (!ClientHelper::is_public_features('GA26')) {
            $query = $query->where(function ($q) {
                $q->where('in_house', '!=', true)->orWhereNull('in_house');
            });
        }

        $items = $query
            ->get(['_id', 'token'])
            ->map(function ($item) {
                return ['key' => $item->_id, 'value' => $item->token];
            })
            ->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function advertisers(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $advertisers = Cache::get($cache_key);
        if (isset($advertisers)) {
            return $advertisers;
        }

        if (!Gate::allows('marketing_advertisers[active=1]')) {
            return [];
        }

        $items = MarketingAdvertiser::query()
            ->get(['_id', 'token'])
            ->map(function ($item) {
                return ['key' => $item->_id, 'value' => $item->token];
            })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function affiliates(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $affiliates = Cache::get($cache_key);
        if (isset($affiliates)) {
            return $affiliates;
        }

        if (!Gate::allows('marketing_affiliates[active=1]')) {
            return [];
        }

        $items = MarketingAffiliate::query()
            ->where('under_review', '=', 1)->orWhere('under_review', '=', null)
            ->get(['_id', 'token'])
            ->map(function ($item) {
                return ['key' => $item->_id, 'value' => $item->token];
            })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function marketing_post_events(string $advertiserId): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $marketing_post_events = Cache::get($cache_key);
        if (isset($marketing_post_events)) {
            return $marketing_post_events;
        }

        $items = MarketingAdvertiserPostEvent::query()
            ->where('advertiser', '=', $advertiserId)
            ->get(['_id', 'name', 'value'])
            ->map(function ($item) {
                return ['key' => $item->_id, 'value' => $item->name . ' (' . $item->value . ')'];
            })->toArray();

        Cache::put($cache_key, $items, 10);

        return $items;
    }

    public function masters(string $type): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $masters = Cache::get($cache_key);
        if (isset($masters)) {
            return $masters;
        }

        if (!Gate::allows('masters[active=1]')) {
            return [];
        }

        $query = Master::query()->where(['type' => $type]);
        if (Gate::allows('masters[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $query = $query->where(function ($q) use ($user_token) {
                $q->where('user_id', '=', $user_token)->orWhere('account_manager', '=', $user_token);
            });
        }

        $items = $query->get(['_id', 'token'])->map(function ($item) {
            return ['key' => $item->_id, 'value' => $item->token];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function master_brands(): array
    {
        return $this->masters('2');
    }

    public function master_affiliates(): array
    {
        return $this->masters('1');
    }

    public function users(string $role = ''): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $users = Cache::get($cache_key);
        if (isset($users)) {
            return $users;
        }

        $query = User::query();
        if (!empty($role)) {
            $query->where('roles', '=', $role);
        }

        $items = $query->get(['_id', 'name', 'account_email', 'status'])->map(function ($item) {
            return ['key' => $item->_id, 'active' => (bool)($item->status ?? false), 'value' => $item->name . (!empty($item->account_email) ? ' (' . $item->account_email . ')' : '')];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function account_managers(): array
    {
        return $this->users('account_manager');
    }

    public function countries(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $countries = Cache::get($cache_key);
        if (isset($countries)) {
            return $countries;
        }

        $items = collect(GeneralHelper::countries(true));
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function languages(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $languages = Cache::get($cache_key);
        if (isset($languages)) {
            return $languages;
        }

        $items = collect(GeneralHelper::languages(true));
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function traffic_sources(): array
    {
        $items = collect([
            'push_traffic' => 'Push Traffic',
            'domain_redirect' => 'Domain Redirect',
            'rtb' => 'RTB',
            'pop' => 'POP',
            'native' => 'NATIVE',
            'google' => 'GOOGLE',
            'facebook' => 'FACEBOOK',
            'ib' => 'IB',
            'seo' => 'SEO',
            'email_marketing' => 'Email Marketing',
            'affiliates' => 'Affiliates',
            'data' => 'Data',
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    /**
     * deprecated
     *
     * @return array
     */
    public function integrations(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $integrations = Cache::get($cache_key);
        if (isset($integrations)) {
            return $integrations;
        }

        $items = Integrations::withoutGlobalScope(new ClientScope)->get(['_id', 'name', 'status', 'p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'p9', 'p10'])->map(function ($item) {
            $item->key = $item->_id;
            $item->value = $item->name;
            // unset($item->_id, $item->name);
            unset($item->name);
            return $item;
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function integrations_1(): array
    {
        return BucketHelper::get_integrations();
    }

    public function lead_statuses(): array
    {
        $items = collect(Leads::status_names());
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]['title']];
        })->toArray();
        return $items;
    }

    public function broker_statuses(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $broker_statuses = Cache::get($cache_key);
        if (isset($broker_statuses)) {
            return $broker_statuses;
        }

        $items = collect(BrokerStatus::status_names());
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function timezones(): array
    {
        $items = collect(\App\Classes\BlockingSchedule::timezones());
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function index(array $dictionaries): array
    {
        $items = [];
        foreach ($dictionaries as $dictionary) {
            $result = [];
            if (method_exists($this, $dictionary)) {
                $reflection = new \ReflectionMethod($this, $dictionary);
                if (!$reflection->isPublic()) {
                    throw new \RuntimeException("The requested dictionary is not public.");
                }
                $data = $this->{$dictionary}();
                if ($data != null && is_array($data)) {
                    $result = $data;
                }
            } else {
                throw new \RuntimeException("The requested dictionary is not found.");
            }
            $items[$dictionary] = $result;
        }
        return $items;
    }

    public function marketing_suite_verticals(): array
    {
        $items = collect([
            'crypto_trading' => 'Crypto/Trading',
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function marketing_suite_platforms(): array
    {
        $items = collect([
            'desktop' => 'Desktop',
            'tablet' => 'Tablet',
            'mobile' => 'Mobile',
            'other' => 'Other',
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function marketing_suite_conversion_types(): array
    {
        $items = collect([
            'registration_plus_first_time_deposit' => 'Registration + First Time Deposit',
            'soi_registration' => 'SOI registration',
            'doi_registration' => 'DOI registration',
            'cpl' => 'CPL',
            'cpi' => 'CPI',
            'pin_submit' => 'Pin Submit',
            'sms_submit' => 'SMS Submit',
            'cps' => 'CPS',
            'push_subscription' => 'Push subscription',
            'cc_submit' => 'CC Submit',
            'free_trial' => 'Free trial'
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function marketing_suite_ristrictions(): array
    {
        $items = collect([
            'NO_ADULT' => 'NO ADULT',
            'NO_MISLEADING' => 'NO MISLEADING',
            'NO_FRAUD' => 'NO FRAUD',
            'NO_BOT' => 'NO BOT',
            'NO_AUTOSUBSCRIPTIONS' => 'NO AUTOSUBSCRIPTIONS',
            'NO_IFRAME' => 'NO IFRAME',
            'NO_INCENT' => 'NO INCENT',
            'NO_CONTENT_LOCK' => 'NO CONTENT LOCK',
            'NO_ANY_SWEEPS_RELATED_CREATIVES_PRELANDERS' => 'NO ANY SWEEPS RELATED CREATIVES/PRELANDERS',
            'NO_WORD_FREE_IN_THE_CREATIVES' => 'NO WORD "FREE" IN THE CREATIVES',
            'NO_POPUP_Traffic' => 'NO POPUP Traffic',
            'NO_PUSH_Traffic' => 'NO PUSH Traffic'
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function marketing_suite_categories(): array
    {
        $items = collect([
            'sweepstakes' => 'Sweepstakes',
            'mobile_content' => 'Mobile content',
            'casino' => 'Casino',
            'lead_generation' => 'Lead generation',
            'streaming' => 'Streaming',
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function marketing_categories(): array
    {
        $items = collect(MarketingCampaign::categories);

        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function billing_payment_companies(): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $broker_statuses = Cache::get($cache_key);
        if (isset($broker_statuses)) {
            return $broker_statuses;
        }

        $items = BillingPaymentCompany::all('_id', 'organization_name')
            // ->sort(function ($a, $b) {
            //     if ($a['organization_name'] == $b['organization_name']) return 0;
            //     return $a['organization_name'] > $b['organization_name'] ? 1 : -1;
            // })
            ->map(function ($item) {
                return ['key' => $item->_id, 'value' => $item->organization_name];
            })
            ->sortBy('value')
            ->values()
            ->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }

    public function master_type_of_calculations(): array
    {
        $items = collect(Master::$type_of_calculations);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function currency_rates(string $datetime): array
    {
        $btcusd = 0;
        if (empty($datetime) || (!empty($datetime) && strtotime($datetime) >= strtotime('-5 minutes'))) {
            $btcusd = CurrencyHelper::GetRate('btc');
        } else {
            $btcusd = CurrencyHelper::GetRateOnDate('btc', $datetime);
            if ($btcusd == 0 && date('Y-m-d', strtotime($datetime)) == date('Y-m-d')) {
                $btcusd = CurrencyHelper::GetRate('btc');
            }
        }

        return ['btcusd' => $btcusd];
    }

    public function reasons_un_payable_leads(): array
    {
        $items = collect([
            'Manager\'s request' => 'Manager\'s request',
            'Finance request (Manager approved)' => 'Finance request (Manager approved)',
            'Finance request' => 'Finance request',
            'other' => 'Other',
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function traffic_deactivation_reasons(): array
    {
        $items = collect([
            'Fraud' => 'Fraud',
            'Company Closed' => 'Company Closed',
            'Duplicated endpoint' => 'Duplicated endpoint',
            'other' => 'Other',
        ]);
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();
        return $items;
    }

    public function regions(string $country_code): array
    {
        $cache_key = $this->cache_key . '_' . __FUNCTION__ . '_' . md5(serialize(func_get_args()));
        $regions = Cache::get($cache_key);
        if (isset($regions)) {
            return $regions;
        }

        $items = collect(GeneralHelper::regions($country_code, true));
        $keys = $items->keys();
        $items = $keys->map(function ($key) use ($items) {
            return ['key' => $key, 'value' => $items[$key]];
        })->toArray();

        Cache::put($cache_key, $items, $this->cache_ttl);

        return $items;
    }
}
