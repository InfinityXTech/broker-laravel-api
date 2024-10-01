<?php

namespace App\Classes\Report;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;

class ReportMeta
{

    public static $pivot_titles = [
        'TrafficEndpoint' => ['title' => 'Traffic Endpoint', 'visible' => true, 'selected' => true],
        'account_manager' => ['title' => 'Account Manager', 'visible' => true, 'selected' => false],
        'brokerId' => ['title' => 'Broker', 'visible' => true, 'selected' => false],
        'sub_publisher' => ['title' => 'Sub Publisher', 'visible' => true, 'selected' => false],
        'CampaignId' => ['title' => 'Campaign', 'visible' => false, 'selected' => false],
        'integrationId' => ['title' => 'Integration ID', 'visible' => false, 'selected' => false],
        'integration' => ['title' => 'Integration', 'visible' => true, 'selected' => false],
        'country' => ['title' => 'Country', 'visible' => true, 'selected' => false],
        'region' => ['title' => 'Region', 'visible' => true, 'selected' => false],
        'region_code' => ['title' => 'Region Code', 'visible' => true, 'selected' => false],
        'city' => ['title' => 'City', 'visible' => true, 'selected' => false],
        'zip_code' => ['title' => 'Zip Code', 'visible' => true, 'selected' => false],
        'funnel_lp' => ['title' => 'Funnel', 'visible' => true, 'selected' => false],
        'deposit_revenue' => ['title' => 'Revenue', 'visible' => false, 'selected' => false, 'total' => true],
        'cost' => ['title' => 'Cost', 'visible' => false, 'selected' => false, 'total' => true],
        'adjustment_amount' => ['title' => 'Adjustment', 'visible' => true, 'selected' => false, 'total' => true],
        'profit' => ['title' => 'Profit', 'visible' => false, 'selected' => false, 'total' => true],
        'Leads' => ['title' => 'Leads', 'visible' => false, 'selected' => false, 'total' => true],
        'BlockedLeads' => ['title' => 'Blocked Leads', 'visible' => false, 'selected' => false, 'total' => true],
        'Depositors' => ['title' => "FTD", 'visible' => false, 'selected' => false, 'total' => true],
        'test_FTD' => ['title' => "Test FTD", 'visible' => false, 'selected' => false, 'total' => true],
        'fake_FTD' => ['title' => "Fake FTD", 'visible' => false, 'selected' => false, 'total' => true],
        'status' => ['title' => "Status", 'visible' => true, 'selected' => false],
        'broker_status' => ['title' => "Broker Status", 'visible' => true, 'allow' => false, 'selected' => false],
        'ApprovedDepositors' => ['title' => 'Approved FTD', 'visible' => false, 'selected' => false, 'total' => true],
        'cpl' => ['title' => 'CPL', 'visible' => false, 'selected' => false],
        'rpl' => ['title' => 'RPL', 'visible' => false, 'selected' => false],
        'pm' => ['title' => 'PM', 'visible' => false, 'selected' => false, 'total' => true, 'total_formula' => true],
        'p_cr' => ['title' => 'P*CR', 'visible' => false, 'selected' => false, 'total' => true, 'total_formula' => true],
        'a_cr' => ['title' => 'ACR', 'visible' => false, 'selected' => false],
        'b_cr' => ['title' => 'BCR', 'visible' => false, 'selected' => false],
        'cr' => ['title' => 'CR', 'visible' => false, 'selected' => false, 'total' => true, 'total_formula' => true],
        'email' => ['title' => 'Email', 'visible' => true, 'selected' => false],
        'first_name' => ['title' => 'First Name', 'visible' => true, 'selected' => false, 'allow' => false],
        'last_name' => ['title' => 'Last Name', 'visible' => true, 'selected' => false, 'allow' => false],
        'phone' => ['title' => 'Phone', 'visible' => true, 'selected' => false, 'allow' => false],
        'ip' => ['title' => 'IP', 'visible' => true, 'selected' => false, 'allow' => false],
        'broker_lead_id' => ['title' => 'Broker Lead ID', 'visible' => true, 'selected' => false],
        'gender' => ['title' => 'Gender', 'visible' => true, 'selected' => false],
        'age' => ['title' => 'Age', 'visible' => true, 'selected' => false],
        'hour' => ['title' => 'Hour', 'visible' => true, 'selected' => false],
        'day' => ['title' => 'Day Of Month', 'visible' => true, 'selected' => false],
        'dayofweek' => ['title' => 'Day Of Week', 'visible' => true, 'selected' => false],
        'month' => ['title' => 'Month', 'visible' => true, 'selected' => false],
        'media_account_id' => ['title' => 'Media Account ID', 'visible' => true, 'selected' => false],
        '_id' => ['title' => 'Lead ID', 'visible' => true, 'selected' => false],
        'OS' => ['title' => 'OS', 'visible' => true, 'selected' => false],
        'OSVersion' => ['title' => 'OS Version', 'visible' => true, 'selected' => false],
        'Browser' => ['title' => 'Browser', 'visible' => true, 'selected' => false],
        'OSBrowser' => ['title' => 'OS Browser', 'visible' => true, 'selected' => false],
        'browser_version' => ['title' => 'Browser Version', 'visible' => true, 'selected' => false],
        'DeviceType' => ['title' => 'Device Type', 'visible' => true, 'selected' => false],
        'd1' => ['title' => 'Dynamic1', 'visible' => true, 'selected' => false],
        'd2' => ['title' => 'Dynamic2', 'visible' => true, 'selected' => false],
        'd3' => ['title' => 'Dynamic3', 'visible' => true, 'selected' => false],
        'd4' => ['title' => 'Dynamic4', 'visible' => true, 'selected' => false],
        'd5' => ['title' => 'Dynamic5', 'visible' => true, 'selected' => false],
        'd6' => ['title' => 'Dynamic6', 'visible' => true, 'selected' => false],
        'd7' => ['title' => 'Dynamic7', 'visible' => true, 'selected' => false],
        'd8' => ['title' => 'Dynamic8', 'visible' => true, 'selected' => false],
        'd9' => ['title' => 'Dynamic9', 'visible' => true, 'selected' => false],
        'd10' => ['title' => 'Dynamic10', 'visible' => true, 'selected' => false],

        'DeviceBrand' => ['title' => 'Device Brand', 'visible' => true, 'selected' => false],
        'device' => ['title' => 'Device', 'visible' => true, 'selected' => false],
        'language' => ['title' => 'Language', 'visible' => true, 'selected' => false],
        'UserLanguage' => ['title' => 'User Language', 'visible' => true, 'selected' => false],
        'connection_type' => ['title' => 'Connection Type', 'visible' => true, 'selected' => false],
        'latitude' => ['title' => 'Latitude', 'visible' => true, 'selected' => false],
        'longitude' => ['title' => 'Longitude', 'visible' => true, 'selected' => false],
        'isp' => ['title' => 'ISP', 'visible' => true, 'selected' => false],
        'Timestamp' => ['title' => 'Timestamp', 'visible' => true, 'selected' => false],
		'depositTimestamp' => ['title' => 'depositTimestamp', 'visible' => true, 'selected' => false, 'allow' => false],
		'endpointDepositTimestamp' => ['title' => 'endpointDepositTimestamp', 'visible' => true, 'selected' => false, 'allow' => false],
        'MasterAffiliate' => ['title' => 'Master Affiliate', 'visible' => true, 'selected' => false],
        'master_affiliate_payout' => ['title' => 'Master Affiliate Cost', 'visible' => false, 'selected' => false, 'total' => true],
        'master_brand' => ['title' => 'Master Brand', 'visible' => true, 'selected' => false],
        'master_brand_payout' => ['title' => 'Master Brand Cost', 'visible' => false, 'selected' => false, 'total' => true],
        'affiliate_cost' => ['title' => 'Affiliate Cost', 'visible' => false, 'selected' => false, 'total' => true],

        /*'broker_crg_revenue' => ['title' => 'Broker CRG Revenue', 'visible' => false, 'selected' => false, 'total' => true],
        'crg_revenue' => ['title' => 'Endpoint CRG Cost', 'visible' => false, 'selected' => false, 'total' => true],

        'broker_crg_expected_deposits' => ['title' => 'Broker CRG FTD', 'visible' => false, 'selected' => false],
        'crg_expected_deposits' => ['title' => 'Endpoint CRG FTD', 'visible' => false, 'selected' => false],*/

        'hit_the_redirect' => ['title' => 'Redirected', 'visible' => true, 'allow' => false, 'selected' => false],

        'crg_already_paid_ftd' => ['title' => 'already paid FTD', 'visible' => false, 'allow' => false, 'selected' => false],
        'broker_crg_already_paid_ftd' => ['title' => 'broker already paid FTD', 'visible' => false, 'allow' => false, 'selected' => false],

        /*'no_crg_deposits' => ['title' => 'No CRG FTD', 'visible' => false, 'allow' => false, 'selected' => false],
        'broker_no_crg_deposits' => ['title' => 'Broker No CRG FTD', 'visible' => false, 'allow' => false, 'selected' => false],
        'no_crg_cpl_cost' => ['title' => 'No CRG & CPL Cost', 'visible' => false, 'allow' => false, 'selected' => false, 'total' => true],
        'broker_no_crg_cpl_revenue' => ['title' => 'Broker No CRG & CPL Revenue', 'visible' => false, 'allow' => false, 'selected' => false, 'total' => true],*/

        'crg_leads' => ['title' => 'is CRG', 'visible' => false, 'selected' => false, 'allow' => false, 'total' => true],
        'cpl_leads' => ['title' => 'is CPL', 'visible' => false, 'selected' => false, 'allow' => false, 'total' => true],
        'broker_crg_leads' => ['title' => 'is Broker CRG', 'visible' => false, 'selected' => false, 'allow' => true, 'total' => true],
        'broker_cpl_leads' => ['title' => 'is Broker CPL', 'visible' => false, 'selected' => false, 'allow' => false, 'total' => true],
        // 'test_lead' => ['title' => 'Test leads', 'visible' => false, 'allow' => false, 'selected' => false, 'total' => true],


		'crg_percentage_id' => ['title' => 'CRG Deal ID', 'visible' => true, 'allow' => false, 'selected' => false],
		'broker_crg_percentage_id' => ['title' => 'Broker CRG Deal ID', 'visible' => true, 'allow' => false,'selected' => false],

        /*'ftd_revenue' => ['title' => 'FTD revenue', 'visible' => false, 'allow' => false, 'selected' => false, 'total' => true],*/

        'mismatch' => ['title' => 'Mismatch', 'name' => 'mismatch', 'visible' => false, 'default_selected' => false, 'total' => false],
        'redirect' => ['title' => 'Redirect', 'name' => 'redirect', 'visible' => false, 'default_selected' => false, 'total' => false],
        'fraudHighRisk' => ['title' => 'Fraud High Risk', 'name' => 'fraudHighRisk', 'visible' => false, 'default_selected' => false, 'total' => false],
        'fraudMediumRisk' => ['title' => 'Fraud Medium Risk', 'name' => 'fraudMediumRisk', 'visible' => false, 'default_selected' => false, 'total' => false],
        'fraudLowRisk' => ['title' => 'Fraud Low Risk', 'name' => 'fraudLowRisk', 'visible' => false, 'default_selected' => false, 'total' => false],

    ];

    public static $pivot_metrics = [
        'revenue' => ['title' => 'Revenue', 'name' => 'deposit_revenue', 'selected' => true, 'total' => true],
        'cost' => ['title' => 'Cost', 'name' => 'cost', 'selected' => true, 'total' => true],
        'profit' => ['title' => 'Profit', 'name' => 'profit', 'selected' => true, 'total' => true],
        'leads' => ['title' => 'Leads', 'name' => 'Leads', 'selected' => true, 'total' => true],
        'ftd' => ['title' => 'FTD', 'name' => 'Depositors', 'selected' => true, 'total' => true],
        // 'test_FTD' => ['title' => 'Test FTD', 'name' => 'test_FTD', 'selected' => true, 'total' => true],
        // 'fake_FTD' => ['title' => 'Fake FTD', 'name' => 'fake_FTD', 'selected' => true, 'total' => true],
        'approved_ftds' => ['title' => 'Approved FTD\'s', 'name' => 'ApprovedDepositors', 'selected' => false, 'total' => true],
        'cr' => ['title' => 'CR', 'name' => 'cr', 'selected' => true, 'total' => true, 'aggregate' => 'formula', 'post_formula' => 'round( __Leads__ > 0 ? ((__Depositors__ / __Leads__) * 100) : 0, 2)'],
        'p_cr' => ['title' => 'P*CR', 'name' => 'p_cr', 'selected' => false, 'total' => true, 'aggregate' => 'formula', 'post_formula' => 'round( __Leads__ > 0 ? ((__ApprovedDepositors__ / __Leads__) * 100) : 0, 2)'],
        'a_cr' => ['title' => 'A*CR', 'name' => 'a_cr', 'selected' => false, 'total' => true],
        'b_cr' => ['title' => 'B*CR', 'name' => 'b_cr', 'selected' => false, 'total' => true],
        'cpl' => ['title' => 'CPL', 'name' => 'cpl', 'selected' => false, 'total' => true, 'aggregate' => 'avg'],
        'profit_margin' => ['title' => 'Profit Margin', 'name' => 'pm', 'selected' => true, 'total' => true],
        'avg_rpl' => ['title' => 'Avg RPL', 'name' => 'rpl', 'selected' => false, 'total' => true],
        'blocked_leads' => ['title' => 'Blocked leads', 'name' => 'BlockedLeads', 'selected' => true, 'total' => true],
        'affiliate_cost' => ['title' => 'Affiliate Cost', 'name' => 'affiliate_cost', 'selected' => false, 'total' => true],
        'master_affiliate_payout' => ['title' => 'Master Affiliate Cost', 'name' => 'master_affiliate_payout', 'selected' => false, 'total' => true],
        'master_brand_payout' => ['title' => 'Master Brand Cost', 'name' => 'master_brand_payout', 'selected' => false, 'total' => true],

        /*'broker_crg_revenue' => ['title' => 'Broker CRG Revenue', 'name' => 'broker_crg_revenue', 'selected' => false, 'total' => true],
        'crg_revenue' => ['title' => 'Endpoint CRG Cost', 'name' => 'crg_revenue', 'selected' => false, 'total' => true],

        'broker_crg_expected_deposits' => ['title' => 'Broker CRG FTD', 'name' => 'broker_crg_expected_deposits', 'selected' => false],
        'crg_expected_deposits' => ['title' => 'Endpoint CRG FTD', 'name' => 'crg_expected_deposits', 'selected' => false],*/

        'crg_already_paid_ftd' => ['title' => 'already paid FTD', 'name' => 'crg_already_paid_ftd', 'allow' => false, 'selected' => true, 'total' => true],
        'broker_crg_already_paid_ftd' => ['title' => 'broker already paid FTD', 'name' => 'broker_crg_already_paid_ftd', 'allow' => false, 'selected' => true, 'total' => true],

        /*'no_crg_deposits' => ['title' => 'No CRG FTD', 'name' => 'no_crg_deposits', 'allow' => false, 'selected' => true, 'total' => true],
        'broker_no_crg_deposits' => ['title' => 'Broker No CRG FTD', 'name' => 'broker_no_crg_deposits', 'allow' => false, 'selected' => true, 'total' => true],

        'no_crg_cpl_cost' => ['title' => 'No CRG & CPL Cost', 'name' => 'no_crg_cpl_cost', 'allow' => false, 'selected' => true, 'total' => true],
        'broker_no_crg_cpl_revenue' => ['title' => 'Broker No CRG & CPL Revenue', 'name' => 'broker_no_crg_cpl_revenue', 'allow' => false, 'selected' => true, 'total' => true],*/

        'crg_leads' => ['title' => 'is CRG', 'name' => 'crg_leads', 'allow' => false, 'selected' => true, 'total' => true],
        'broker_crg_leads' => ['title' => 'is Broker CRG', 'name' => 'broker_crg_leads', 'allow' => false, 'selected' => true, 'total' => true],

        'cpl_leads' => ['title' => 'is CPL', 'name' => 'cpl_leads', 'allow' => false, 'selected' => true, 'total' => true],
        'broker_cpl_leads' => ['title' => 'is Broker CPL', 'name' => 'broker_cpl_leads', 'allow' => false, 'selected' => true, 'total' => true],

        'test_lead' => ['title' => 'Test leads', 'name' => 'test_lead', 'allow' => true, 'selected' => true, 'total' => true],
        /*'ftd_revenue' => ['title' => 'FTD revenue', 'name' => 'ftd_revenue', 'allow' => false, 'selected' => true, 'total' => true],*/

        'mismatch' => ['title' => 'Mismatch', 'visible' => true, 'name' => 'mismatch', 'total' => true, 'selected' => false, 'aggregate' => 'avg'],
        'redirect' => ['title' => 'Redirect', 'visible' => true, 'name' => 'redirect', 'total' => true, 'selected' => false, 'aggregate' => 'avg'],
        'fraudHighRisk' => ['title' => 'Fraud High Risk', 'visible' => true, 'name' => 'fraudHighRisk', 'selected' => false, 'total' => true, 'aggregate' => 'avg'],
        'fraudMediumRisk' => ['title' => 'Fraud Medium Risk', 'visible' => true, 'name' => 'fraudMediumRisk', 'selected' => false, 'total' => true, 'aggregate' => 'avg'],
        'fraudLowRisk' => ['title' => 'Fraud Low Risk', 'visible' => true, 'name' => 'fraudLowRisk', 'selected' => false, 'total' => true, 'aggregate' => 'avg'],

    ];

    public function __construct()
    {
    }

    public function render_with_permission($is_deny, $value, $mask = '*****')
    {
        return ($is_deny ? $mask : $value);
    }

    public static function get_titles()
    {
        $user = Auth()->user(); // auth::get_current_user_data();
        $custom_allow = function ($key, $pivot) use ($user) {
            // TODO: Access
            // if (isset($pivot['allow']) && $pivot['allow'] === false) {
            //     $permission = customUserAccess::get_permission('pivots/' . $key);
            //     return ($permission === true);
            // }
            return true;
        };

        $array = array();
        foreach (ReportMeta::$pivot_titles as $pivot_key => $pivot) {
            // if ($custom_allow($pivot_key, $pivot)) {
            {
                $array[$pivot_key] = $pivot['title'];
            }
        }

        return $array;
    }

    public static function get_brokers_list()
    {
        $find = [];
        $access = false;
        $where = ['partner_type' => '1'];

        // TODO: Access
        // $permissions = permissionsManagement::get_user_permissions('reports');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('reports[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        } else {
            $access = true;
        }
        $mongo = new MongoDBObjects('partner', $where);
        $find = $mongo->findMany();
        return [$find, $access];
    }

    // public static function get_campaigns_list()
    // {
    //     $find = [];
    //     $access = false;
    //     $permissions = permissionsManagement::get_user_permissions('reports');
    //     $where = [];
    //     if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
    //         $user_token = auth::get_current_user_token();
    //         $where['$or'] = [
    //             ['user_id' => $user_token],
    //             ['account_manager' => $user_token],
    //         ];
    //     } else $access = true;
    //     $mongo = new MongoDBObjects('campaigns', $where);
    //     $find = $mongo->findMany();
    //     return [$find, $access];
    // }

    public static function get_traffic_endpoint_list()
    {
        $find = [];
        $access = false;
        $where = [];

        // TODO: access
        // $permissions = permissionsManagement::get_user_permissions('reports');
        // if ($permissions && isset($permissions['is_only_assigned']) && $permissions['is_only_assigned']) {
        if (Gate::allows('reports[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $where['$or'] = [
                ['user_id' => $user_token],
                ['account_manager' => $user_token],
            ];
        } else {
            $access = true;
        }
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $find = $mongo->findMany();
        return [$find, $access];
    }

    public static function get_account_manager_list()
    {
        list($find, $access) = self::get_traffic_endpoint_list();
        $result = [];
        foreach ($find as $supply) {
            $account_manager = $supply['account_manager'] ?? null;
            if ($account_manager && !isset($result[$account_manager])) {
                $result[$account_manager] = [
                    '_id' => $account_manager,
                    'name' => auth::get_user($account_manager)['name']
                ];
            }
        }
        return [array_values($result), $access];
    }
}
