<?php

namespace App\Repository\Leads;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use stdClass;

use Exception;
use App\Models\User;
use App\Models\Leads;
use App\Models\Master;
use App\Helpers\CryptHelper;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Models\Brokers\BrokerCrg;
use App\Classes\History\HistoryDB;
use App\Repository\BaseRepository;
use App\Models\Brokers\BrokerPayout;
use App\Models\Masters\MasterPayout;
use Illuminate\Support\Facades\Auth;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Classes\History\HistoryDBAction;
use App\Models\Alert;
use App\Models\Brokers\BrokerPrivateDeal;
use App\Repository\Leads\ILeadsRepository;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Investigate\InvestigateRepository;
use App\Models\TrafficEndpoints\TrafficEndpointPayout;
use App\Models\TrafficEndpoints\TrafficEndpointPrivateDeal;
use App\Repository\TrafficEndpoints\TrafficEndpointRepository;
use Illuminate\Support\Facades\Log;

class LeadsRepository extends BaseRepository implements ILeadsRepository
{
    protected $model;

    public function __construct(Leads $model)
    {
        $this->model = $model;
    }

    public function test_lead(string $leadId): bool
    {
        $update = array();

        // if ((int)$this->post['status'] == 1) {
        $update['status'] = 'Test Lead';
        $update['test_lead'] = 1;
        $update['crg_deal'] = false;
        $update['broker_crg_deal'] = false;
        $update['master_brand_payout'] = 0;
        $update['master_affiliate_payout'] = 0;
        $update['cost'] = 0;
        $update['deposit_revenue'] = 0;
        $update['revenue'] = 0;

        // } elseif ((int)$this->post['status'] == 2) {
        // 	$update['status'] = 'Wrong Number';
        // 	$update['test_lead'] = 1;
        // } elseif ((int)$this->post['status'] == 3) {
        // 	$update['status'] = 'Low Quality';
        // 	$update['test_lead'] = 1;
        // } elseif ((int)$this->post['status'] == 4) {
        // 	$update['status'] = 'Invalid/Not Payable';
        // 	$update['notpayable'] = true;
        // }

        $where = array();
        $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);

        $m = new MongoDBObjects('leads', $where);
        return $m->update($update);
    }

    public function createAlerts(string $leadId, array $payload): bool
    {
        $model = $this->model->where('_id', $leadId)->first();
        return (bool) $model->alerts()->create($payload);
    }

    public function deleteAlerts(string $alertID): bool
    {
        $model = Alert::where('_id', $alertID)->first();
        return (bool) $model->update(['status' => 3]);
    }

    public function listAlerts(): Collection
    {
        // return Collection::empty();
        return Alert::all();
    }

    public function fireftd(string $leadId, bool $fake_deposit = false): bool
    {
        // if (!customUserAccess::is_allow('fire_ftd')) {
        // 	throw new Exception(permissionsManagement::get_error_message());
        // }

        // $google_auth_key = $this->post['google_auth_key'];
        // if (!GoogleAuth::isAllowStaticAction($google_auth_key)) {
        // 	throw new Exception('Google authentication code is not valid or session expired');
        // }

        $where = array();
        $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);

        $where['depositor'] = false;
        $where['match_with_broker'] = 1;

        $update = array();
        $update['deposit_by_pixel'] = true;
        $update['deposit_by_pixel_user'] = Auth::id();

        $mongo = new MongoDBObjects('leads', $where);

        $var = date("Y-m-d H:i:s");
        if ($fake_deposit) {
            $lead = $mongo->find();

            $update['status'] = 'Depositor';
            $update['fakeDepositor'] = true;

            if ($lead['isCPL'] == false) {
                $lead['cost'] = $lead['crg_payout'] ?? 0;
            }

            if (($lead['test_lead'] ?? 0) == 1) {
                $update['cost'] = 0;
                $update['master_affiliate_payout'] = 0;
            } else if ($lead['isCPL'] == false) {
                $update['cost'] = $lead['crg_payout'] ?? 0;
                $update['master_affiliate_payout'] = $lead['crg_master_payout'] ?? 0;
            }

            $update['endpointDepositTimestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

            $srv = new TrafficEndpointRepository();
            $srv->postbackFTD($lead);
        } else {
            //$update['status'] = 'Depositor';
            $update['depositor'] = true;
            $update['depositTimestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
            $update['realDepositTimestamp'] =  new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        }

        return $mongo->update($update);
    }

    private function _get_crg_lead(string $leadId, bool $changed = false): array
    {
        $result = [];
        try {

            $where = array();
            $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);
            $mongo = new MongoDBObjects('leads', $where);

            $broker_crg_percentage_id_field = 'broker_crg_percentage_id';
            $broker_crg_payout_id_field = 'broker_crg_payout_id';
            $broker_crg_payout_field = 'broker_crg_payout';
            $broker_crg_payout_manual_field = 'broker_crg_payout_manual';

            $crg_percentage_id_field = 'crg_percentage_id';
            $crg_payout_id_field = 'crg_payout_id';
            $crg_payout_field = 'crg_payout';
            $crg_payout_manual_field = 'crg_payout_manual';

            if ($changed) {
                $broker_crg_percentage_id_field = 'broker_changed_crg_percentage_id';
                $broker_crg_payout_id_field = 'broker_changed_crg_payout_id';
                $broker_crg_payout_field = 'broker_changed_crg_payout';
                $broker_crg_payout_manual_field = 'broker_changed_crg_payout_manual';

                $crg_percentage_id_field = 'changed_crg_percentage_id';
                $crg_payout_id_field = 'changed_crg_payout_id';
                $crg_payout_field = 'changed_crg_payout';
                $crg_payout_manual_field = 'changed_crg_payout_manual';
            }

            $leadData = $mongo->find();

            // --- broker_deals --- //
            $where = [
                'broker' => $leadData['brokerId'],
                // 'status' => '1',
                'type' => ['$in' => ['2', '3']],
                'country_code' => strtolower($leadData['country'])
            ];

            $mongo = new MongoDBObjects('broker_crg', $where);
            $array = $mongo->findMany();

            $broker_deals = [];

            foreach ($array as $crg) {
                $pid = (array)$crg['_id'];
                $pid = $pid['oid'];
                // echo '<pre>' . print_r($crg,true) . '</pre>';

                // if (isset($crg['end_date']) && !empty($crg['end_date'])) {
                //     if (strtotime($crg['end_date']) < strtotime(date('d-m-Y'))) {
                //         continue;
                //     }
                // }

                $only_integrations = array_filter((array)($crg['only_integrations'] ?? []), function ($item) {
                    return !empty($item);
                });
                if (
                    count($only_integrations) > 0 &&
                    !in_array($leadData['integrationId'], $only_integrations)
                ) {
                    continue;
                }

                $crg_endpoints = (array)($crg['endpoint'] ?? null ?: []);
                if (!empty($crg_endpoints) && !in_array($leadData['TrafficEndpoint'], $crg_endpoints)) {
                    continue;
                }

                // language as string
                if (isset($crg['language_code']) && is_string($crg['language_code']) && !empty($crg['language_code'])) {
                    if ($crg['language_code'] != $leadData['language']) {
                        continue;
                    }
                }

                // language as array
                if (!is_string($crg['language_code']) && count($crg['language_code'] ?? []) > 0) {
                    if (!in_array($leadData['language'], (array)$crg['language_code'])) {
                        continue;
                    }
                }

                $crg_ignore_endpoints = (array)($crg['ignore_endpoints'] ?? null ?: []);
                if (!empty($crg_ignore_endpoints) && in_array($leadData['TrafficEndpoint'], $crg_ignore_endpoints)) {
                    continue;
                }

                $broker_deals[] = [
                    'value' => $pid,
                    'default' => ($leadData[$broker_crg_percentage_id_field] ?? null) == $pid,
                    'label' => $crg['name'] . ' (' . $crg['min_crg'] . '%)'
                ];
            }
            // --- broker_deals --- //

            // --- broker_deals payout --- //
            $where = [
                'broker' => $leadData['brokerId'],
                // 'status' => '1',
                'type' => ['$in' => ['1', '3']],
                'country_code' => strtolower($leadData['country'])
            ];

            $mongo = new MongoDBObjects('broker_crg', $where);
            $array = $mongo->findMany();

            $payouts_selected = false;
            $is_payouts_selected = false;
            $broker_payouts = [];

            foreach ($array as $crg) {
                $pid = (array)$crg['_id'];
                $pid = $pid['oid'];

                // if (isset($crg['end_date']) && !empty($crg['end_date'])) {
                //     if (strtotime($crg['end_date']) < strtotime(date('d-m-Y'))) {
                //         continue;
                //     }
                // }

                $only_integrations = array_filter((array)($crg['only_integrations'] ?? []), function ($item) {
                    return !empty($item);
                });
                if (
                    count($only_integrations) > 0 &&
                    !in_array($leadData['integrationId'], $only_integrations)
                ) {
                    continue;
                }

                // language as string
                if (isset($crg['language_code']) && is_string($crg['language_code']) && !empty($crg['language_code'])) {
                    if ($crg['language_code'] != $leadData['language']) {
                        continue;
                    }
                }

                // language as array
                if (!is_string($crg['language_code']) && count($crg['language_code'] ?? []) > 0) {
                    if (!in_array($leadData['language'], (array)$crg['language_code'])) {
                        continue;
                    }
                }

                $crg_endpoints = (array)($crg['endpoint'] ?? null ?: []);
                if (!empty($crg_endpoints) && !in_array($leadData['TrafficEndpoint'], $crg_endpoints)) {
                    continue;
                }

                $crg_ignore_endpoints = (array)($crg['ignore_endpoints'] ?? null ?: []);
                if (!empty($crg_ignore_endpoints) && in_array($leadData['TrafficEndpoint'], $crg_ignore_endpoints)) {
                    continue;
                }

                $payouts_selected = (isset($leadData[$broker_crg_payout_id_field]) && $leadData[$broker_crg_payout_id_field] == $pid);
                $is_payouts_selected = $is_payouts_selected || $payouts_selected;

                $broker_payouts[] = [
                    'value' => 'crg_' . $pid,
                    'default' => $payouts_selected,
                    'label' => $crg['name'] . ' ($' . $crg['payout'] . ')'
                ];
            }

            $where = [
                'broker' => $leadData['brokerId'],
                'cost_type' => '1', // CPA
                'country_code' => strtolower($leadData['country'])
            ];
            $mongo = new MongoDBObjects('broker_payouts', $where);
            $array = $mongo->findMany();
            foreach ($array as $item) {
                $is = !$payouts_selected && isset($leadData[$broker_crg_payout_field]) && ((int)$leadData[$broker_crg_payout_field] ?? 0) == ((int)$item['payout'] ?? 0);
                $is_payouts_selected = $is_payouts_selected || $is;

                $broker_payouts[] = [
                    'value' => 'payout_' . $item['_id'],
                    'default' => $is,
                    'label' => '$' . $item['payout']
                ];
            }

            $broker_payouts[] = [
                'value' => 'other',
                'default' => !$is_payouts_selected,
                'label' => 'Other'
            ];

            // --- broker_deals payout --- //

            // --- deals --- //
            $where = [
                'TrafficEndpoint' => $leadData['TrafficEndpoint'],
                // 'status' => '1',
                'type' => ['$in' => ['2', '3']],
                'country_code' => strtolower($leadData['country'])
            ];

            $mongo = new MongoDBObjects('endpoint_crg', $where);
            $array = $mongo->findMany();

            $deals = [];

            foreach ($array as $crg) {
                $pid = (array)$crg['_id'];
                $pid = $pid['oid'];

                // if (isset($crg['end_date']) && !empty($crg['end_date'])) {
                //     if (strtotime($crg['end_date']) < strtotime(date('d-m-Y'))) {
                //         continue;
                //     }
                // }

                $only_integrations = array_filter((array)($crg['only_integrations'] ?? []), function ($item) {
                    return !empty($item);
                });
                if (
                    count($only_integrations) > 0 &&
                    !in_array($leadData['integrationId'], $only_integrations)
                ) {
                    continue;
                }

                // language as string
                if (isset($crg['language_code']) && is_string($crg['language_code']) && !empty($crg['language_code'])) {
                    if ($crg['language_code'] != $leadData['language']) {
                        continue;
                    }
                }

                // language as array
                if (!is_string($crg['language_code']) && count($crg['language_code'] ?? []) > 0) {
                    if (!in_array($leadData['language'], (array)$crg['language_code'])) {
                        continue;
                    }
                }

                if (isset($crg['endpoint']) && !empty($crg['endpoint'])) {
                    if ($crg['endpoint'] != $leadData['TrafficEndpoint']) {
                        continue;
                    }
                }

                if (isset($crg['ignore_endpoints']) && count($crg['ignore_endpoints']) > 0 && in_array($leadData['TrafficEndpoint'], (array)$crg['ignore_endpoints'])) {
                    continue;
                }

                $deals[] = [
                    'value' => $pid,
                    'default' => (isset($leadData[$crg_percentage_id_field]) && $leadData[$crg_percentage_id_field] == $pid),
                    'label' => $crg['name'] . ' (' . $crg['min_crg'] . '%)'
                ];
            }

            // --- deals --- //

            // --- deals payout --- //
            $where = [
                'TrafficEndpoint' => $leadData['TrafficEndpoint'],
                // 'status' => '1',
                'type' => ['$in' => ['1', '3']],
                'country_code' => strtolower($leadData['country'])
            ];

            $mongo = new MongoDBObjects('endpoint_crg', $where);
            $array = $mongo->findMany();

            $payouts_selected = false;
            $is_payouts_selected = false;
            $payouts = [];

            foreach ($array as $crg) {
                $pid = (array)$crg['_id'];
                $pid = $pid['oid'];

                // if (isset($crg['end_date']) && !empty($crg['end_date'])) {
                //     if (strtotime($crg['end_date']) < strtotime(date('d-m-Y'))) {
                //         continue;
                //     }
                // }

                $only_integrations = array_filter((array)($crg['only_integrations'] ?? []), function ($item) {
                    return !empty($item);
                });
                if (
                    count($only_integrations) > 0 &&
                    !in_array($leadData['integrationId'], $only_integrations)
                ) {
                    continue;
                }

                // language as string
                if (isset($crg['language_code']) && is_string($crg['language_code']) && !empty($crg['language_code'])) {
                    if ($crg['language_code'] != $leadData['language']) {
                        continue;
                    }
                }

                // language as array
                if (!is_string($crg['language_code']) && count($crg['language_code'] ?? []) > 0) {
                    if (!in_array($leadData['language'], (array)$crg['language_code'])) {
                        continue;
                    }
                }

                if (isset($crg['endpoint']) && !empty($crg['endpoint'])) {
                    if ($crg['endpoint'] != $leadData['TrafficEndpoint']) {
                        continue;
                    }
                }

                if (isset($crg['ignore_endpoints']) && count($crg['ignore_endpoints']) > 0 && in_array($leadData['TrafficEndpoint'], (array)$crg['ignore_endpoints'])) {
                    continue;
                }

                $payouts_selected = (isset($leadData[$crg_payout_id_field]) && $leadData[$crg_payout_id_field] == $pid);
                $is_payouts_selected = $is_payouts_selected || $payouts_selected;

                $payouts[] = [
                    'value' => 'crg_' . $pid,
                    'default' => $payouts_selected,
                    'label' => $crg['name'] . ' ($' . $crg['payout'] . ')'
                ];
            }

            $where = [
                'TrafficEndpoint' => $leadData['TrafficEndpoint'],
                'cost_type' => '1', // CPA
                'country_code' => strtolower($leadData['country'])
            ];
            $mongo = new MongoDBObjects('endpoint_payouts', $where);
            $array = $mongo->findMany();
            foreach ($array as $item) {
                $is = (!$payouts_selected && isset($leadData[$crg_payout_field]) && (((int)$leadData[$crg_payout_field] ?? 0) == ((int)$item['payout'] ?? 0)));
                $is_payouts_selected = $is_payouts_selected || $is;
                $payouts[] = [
                    'value' => 'payout_' . $item['_id'],
                    'default' => $is,
                    'label' =>  '$' . $item['payout']
                ];
            }

            $payouts[] = [
                'value' => 'other',
                'default' => !$is_payouts_selected,
                'label' =>  'Other'
            ];

            // --- deals payout --- //

            $get_default_value = function ($options) {
                foreach ($options as $option) {
                    if ($option['default'] ?? false) {
                        return $option['value'];
                    }
                }
                return null;
            };

            if (($leadData['broker_crg_deal'] ?? false) == true) {
                $result[$broker_crg_percentage_id_field] = $get_default_value($broker_deals);
                $result[$broker_crg_payout_field] = $get_default_value($broker_payouts);
                $result[$broker_crg_payout_manual_field] = $leadData[$broker_crg_payout_field] ?? null;
            } else {
                $result[$broker_crg_percentage_id_field] = null;
                $result[$broker_crg_payout_field] = null;
                $result[$broker_crg_payout_manual_field] = null;
            }

            if (empty($result[$broker_crg_percentage_id_field]) && empty($result[$broker_crg_payout_manual_field]) && $result[$broker_crg_payout_field] == 'other') {
                $result[$broker_crg_payout_field] = null;
            }

            $result['broker_deals'] = $broker_deals;
            $result['broker_payouts'] = $broker_payouts;

            if (($leadData['crg_deal'] ?? false) == true) {
                $result[$crg_percentage_id_field] = $get_default_value($deals);
                $result[$crg_payout_field] = $get_default_value($payouts);
                $result[$crg_payout_manual_field] = $leadData[$crg_payout_field] ?? null;
            } else {
                $result[$crg_percentage_id_field] = null;
                $result[$crg_payout_field] = null;
                $result[$crg_payout_manual_field] = null;
            }

            if (empty($result[$crg_percentage_id_field]) && empty($result[$crg_payout_manual_field]) && $result[$crg_payout_field] == 'other') {
                $result[$crg_payout_field] = null;
            }

            $result['deals'] = $deals;
            $result['payouts'] = $payouts;
        } catch (\Exception $ex) {
            return ['success' => false, 'error' => $ex->getMessage()];
        }
        return $result;
    }

    public function get_crg_lead(string $leadId): array
    {
        $result = [];
        try {
            $result = $this->_get_crg_lead($leadId);
        } catch (\Exception $ex) {
            return ['success' => false, 'error' => $ex->getMessage()];
        }
        return $result;
    }

    public function mark_crg_lead(string $leadId, array $payload): bool
    {
        try {

            $where = array();
            $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);
            $mongo = new MongoDBObjects('leads', $where);
            $lead = $mongo->find();

            if (!empty($payload['crg_percentage_id'] ?? '') && ($lead['isCPL'] ?? false)) {
                throw new Exception('This lead is CPL. It can\'t be CRG');
            }

            if (!empty($payload['broker_crg_percentage_id'] ?? '') && ($lead['broker_cpl'] ?? false)) {
                throw new Exception('This lead is CPL. It can\'t be CRG');
            }

            $update = [
                'crg_deal' => !!($payload['crg_percentage_id'] ?? false),
                'broker_crg_deal' => !!($payload['broker_crg_percentage_id'] ?? false),
                // 'crg_ftd_uncount' => false,
                // 'broker_crg_ftd_uncount' => false
            ];

            if ($update['crg_deal']) {

                $update['crg_percentage_id'] = $payload['crg_percentage_id'] ?? '';
                $update['crg_payout_id'] = '';
                $update['crg_payout'] = '';

                $deal = explode('_', $payload['crg_payout'], 2);
                $deal_type = $deal[0];
                $deal_id = $deal[1] ?? null;
                if ($deal_type == 'crg') {
                    $model = TrafficEndpointPrivateDeal::findOrFail($deal_id);
                    $update['crg_payout_id'] = $deal_id;
                    $update['crg_payout'] = $model->payout;
                } else if ($deal_type == 'payout') {
                    $model = TrafficEndpointPayout::findOrFail($deal_id);
                    $update['crg_payout'] = $model->payout;
                } else if ($deal_type == 'other') {
                    $update['crg_payout'] = $payload['crg_payout_manual'];
                } else {
                    throw new \Exception('Unknown deal type: ' . $deal_type);
                }

                // crg_percentage
                $model = TrafficEndpointPrivateDeal::findOrFail($update['crg_percentage_id']);
                $update['crg_percentage'] = $model->min_crg;
                $update['crg_percentage_period'] = $model->calc_period_crg;
            }

            if ($update['broker_crg_deal']) {

                $update['broker_crg_percentage_id'] = $payload['broker_crg_percentage_id'] ?? '';
                $update['broker_crg_payout_id'] = '';
                $update['broker_crg_payout'] = '';

                $deal = explode('_', $payload['broker_crg_payout'], 2);
                $deal_type = $deal[0];
                $deal_id = $deal[1] ?? null;
                if ($deal_type == 'crg') {
                    $model = BrokerPrivateDeal::findOrFail($deal_id);
                    $update['broker_crg_payout_id'] = $deal_id;
                    $update['broker_crg_payout'] = $model->payout;
                } else if ($deal_type == 'payout') {
                    $model = BrokerPayout::findOrFail($deal_id);
                    $update['broker_crg_payout'] = $model->payout;
                } else if ($deal_type == 'other') {
                    $update['broker_crg_payout'] = $payload['broker_crg_payout_manual'];
                } else {
                    throw new \Exception('Unknown deal type: ' . $deal_type);
                }

                // crg_percentage
                $model = BrokerPrivateDeal::findOrFail($update['broker_crg_percentage_id']);
                $update['broker_crg_percentage'] = $model->min_crg;
                $update['broker_crg_percentage_period'] = $model->calc_period_crg;
            }

            foreach ($update as $k => $v) {
                if ($k != 'crg_deal' && $k != 'broker_crg_deal') {
                    $lead[$k] = $v;
                }
            }

            // lead timestamp
            $v = $lead['Timestamp'];
            $mil = ((array)$v)['milliseconds'];
            $seconds = $mil / 1000;
            $dt = date("d-m-Y", $seconds);

            // period
            $get_period = function ($dt, $period) {
                $start = null;
                $end = null;
                $period = (int)$period;
                switch ($period) {
                    case 1: { // Daily
                            $start = $dt;
                            $end = $dt;
                            break;
                        }
                    case 2: { // Weekly
                            $day_of_week = date("N", strtotime($dt)) - 1;
                            $start = date("Y-m-d", strtotime($dt . " -$day_of_week days"));
                            $end = date("Y-m-d", strtotime($start . " +6 days"));
                            break;
                        }
                    case 3: { // Monthly
                            $month = date("m", strtotime($dt));
                            $start = date('Y-' . $month . '-01');
                            $date = new \DateTime($start);
                            $date->setTime(23, 59, 59);
                            $date->modify('last day of this month');
                            $end = $date->format('Y-m-d');
                            break;
                        }
                }
                return [
                    'start' => (isset($start) ? new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d", strtotime($start)) . " 00:00:00") * 1000) : null),
                    'end' => (isset($end) ? new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d", strtotime($end)) . " 23:59:59") * 1000) : null)
                ];
            };

            $period = $get_period($dt, $lead['crg_percentage_period'] ?? -1);

            // check ended period
            if (!empty($lead['crg_percentage_id']) && isset($period['end'])) {
                $mil = ((array)$period['end'])['milliseconds'];
                $end_timestamp = $mil / 1000; //seconds
                $update['crg_finall_calculated'] = (strtotime(date("d-m-Y 00:00:00")) >= strtotime(date("d-m-Y 00:00:00", $end_timestamp)));
            }

            if (($update['crg_deal'] ?? false) != ($lead['crg_deal'] ?? false)) {

                // log
                $fields = ['crg_deal'];
                $data = ['crg_deal' => $update['crg_deal']];
                $category = 'crg';
                $reasons_change_crg = $payload['reason_change_crg'] ?? $payload['reason_change_crg2'] ?? '';
                HistoryDB::add(HistoryDBAction::Update, 'leads', $data, $leadId, '', $fields, $category, $reasons_change_crg);

                // $update['crg_finall_calculated'] = false;
                $update['crg_recalculate'] = true;

                if ((bool)($lead['depositor'] ?? false) == true) {
                    $update['crg_already_paid'] = false;
                    $update['crg_ftd_uncount'] = false;
                    $update['crg_ftd_uncount_timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000);
                    $update['crg_ftd_uncount_handler'] = 'manual';
                }

                // --- crg_percentage_id --- //
                $where = [
                    'crg_deal' => true,
                    'crg_percentage_id' => $lead['crg_percentage_id'],
                    'crg_percentage_period' => $lead['crg_percentage_period'],
                    'Timestamp' => ['$gte' => $period['start'], '$lte' => $period['end']]
                ];
                $mongo_percentage = new MongoDBObjects('leads', $where);
                $leads = $mongo_percentage->findMany();
                if (!isset($leads)) $leads = [];
                $in = [];
                foreach ($leads as $l) {
                    $lead_id = (array)$l['_id'];
                    $lead_id = $lead_id['oid'];
                    $in[] = new \MongoDB\BSON\ObjectId($lead_id);
                }
                $where = array();
                $where = ['_id' => ['$in' => $in]];
                $mongo_percentage = new MongoDBObjects('leads', $where);
                $update2 = ['crg_recalculate' => true];
                $mongo_percentage->updateMulti($update2);
                // --- crg_percentage_id ---//

            }

            $broker_period = $get_period($dt, $lead['broker_crg_percentage_period'] ?? -1);

            // check ended period
            if (!empty($lead['broker_crg_percentage_id']) && isset($broker_period['end'])) {
                $mil = ((array)$broker_period['end'])['milliseconds'];
                $end_timestamp = $mil / 1000; //seconds
                $update['broker_crg_finall_calculated'] = (strtotime(date("d-m-Y 00:00:00")) >= strtotime(date("d-m-Y 00:00:00", $end_timestamp)));
            }

            if (($update['broker_crg_deal'] ?? false) != ($lead['broker_crg_deal'] ?? false)) {

                // log
                $fields = ['broker_crg_deal'];
                $data = ['broker_crg_deal' => $update['broker_crg_deal']];
                $category = 'broker_crg';
                $reasons_change_crg = $payload['broker_reason_change_crg2'] ?? $payload['broker_reason_change_crg'] ?? '';
                HistoryDB::add(HistoryDBAction::Update, 'leads', $data, $leadId, '', $fields, $category, $reasons_change_crg);

                // $update['broker_crg_finall_calculated'] = false;
                $update['broker_crg_recalculate'] = true;

                if ((bool)($lead['depositor'] ?? false) == true) {
                    $update['broker_crg_already_paid'] = false;
                    $update['broker_crg_ftd_uncount'] = false;
                    $update['broker_crg_ftd_uncount_timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000);
                    $update['broker_crg_ftd_uncount_handler'] = 'manual';
                }

                // --- broker_crg_percentage_id --- //
                $where = [
                    'broker_crg_deal' => true,
                    'broker_crg_percentage_id' => $lead['broker_crg_percentage_id'] ?? '',
                    'broker_crg_percentage_period' => $lead['broker_crg_percentage_period'] ?? '',
                    'Timestamp' => ['$gte' => $broker_period['start'], '$lte' => $broker_period['end']]
                ];
                $mongo_percentage = new MongoDBObjects('leads', $where);
                $leads = $mongo_percentage->findMany();
                if (!isset($leads)) $leads = [];
                $in = [];
                foreach ($leads as $l) {
                    $lead_id = (array)$l['_id'];
                    $lead_id = $lead_id['oid'];
                    $in[] = new \MongoDB\BSON\ObjectId($lead_id);
                }
                $where = array();
                $where = ['_id' => ['$in' => $in]];
                $mongo_percentage = new MongoDBObjects('leads', $where);
                $update2 = ['broker_crg_recalculate' => true];
                $mongo_percentage->updateMulti($update2);
                // --- broker_crg_percentage_id ---//

            }

            // $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);
            // $mongo = new MongoDBObjects('leads', $where);
            return $mongo->update($update);
            // 	$data = ['success' => true];
            // }
        } catch (\Exception $ex) {
            throw $ex;
            // $data = ['success' => false, 'error' => $ex->getMessage()];
        }
        return false;
    }

    public function get_crg_ftd(string $leadId): array
    {
        $result = [];
        try {
            $result = $this->_get_crg_lead($leadId, true);
        } catch (\Exception $ex) {
            return ['success' => false, 'error' => $ex->getMessage()];
        }
        return $result;
    }

    public function change_crg_ftd(string $leadId, array $payload): bool
    {
        $model = Leads::findOrFail($leadId);

        // broker
        if (!empty($payload['broker_changed_crg_percentage_id'])) {

            $broker_update = BrokerCrg::findOrFail($payload['broker_changed_crg_percentage_id'])->toArray();

            $update = [];

            if (
                // ((int)$broker_update['status'] ?? 0) == 1 &&
                !empty($payload['broker_changed_crg_percentage_id']) &&
                !empty($broker_update['calc_period_crg'])
            ) {

                $crg_payout = $broker_update['payout'] ?? 0;
                $crg_payout_id = null;
                if (isset($payload['broker_changed_crg_payout_manual']) && (int)$payload['broker_changed_crg_payout_manual'] > 0) {
                    $crg_payout = (int)$payload['broker_changed_crg_payout_manual'];
                } else if (!empty($payload['broker_changed_crg_payout'])) {
                    $deal = explode('_', $payload['broker_changed_crg_payout'], 2);
                    $deal_type = $deal[0];
                    $deal_id = $deal[1] ?? null;
                    if ($deal_type == 'crg') {
                        $b_model = BrokerCrg::findOrFail($deal_id);
                        $crg_payout_id = $deal_id;
                        $crg_payout = (int)$b_model->payout;
                    } else if ($deal_type == 'payout') {
                        $b_model = BrokerPayout::findOrFail($deal_id);
                        $crg_payout = (int)$b_model->payout;
                    } else {
                        throw new \Exception('Unknown deal type: ' . $deal_type);
                    }
                }

                $update = [
                    'broker_changed_crg_percentage_id' => $payload['broker_changed_crg_percentage_id'],
                    'broker_changed_crg_percentage_period' => $broker_update['calc_period_crg'],
                    'broker_changed_crg_percentage' => ((int)$broker_update['min_crg'] ?? 0),
                    'broker_changed_crg_payout_id' => $crg_payout_id,
                    'broker_changed_crg_payout' => (int)$crg_payout
                ];
                $model->update($update);
            }
        } else {
            $update = [
                'broker_changed_crg_percentage_id',
                'broker_changed_crg_percentage_period',
                'broker_changed_crg_percentage',
                'broker_changed_crg_payout_id',
                'broker_changed_crg_payout'
            ];
            $model->unset($update);
        }

        //endpoint
        if (!empty($payload['changed_crg_percentage_id'])) {

            $endpoint_update = TrafficEndpointPrivateDeal::findOrFail($payload['changed_crg_percentage_id'])->toArray();

            $update = [];

            if (
                // ((int)$endpoint_update['status'] ?? 0) == 1 &&
                !empty($payload['changed_crg_percentage_id']) &&
                !empty($endpoint_update['calc_period_crg'])
            ) {

                $crg_payout = $endpoint_update['payout'] ?? 0;
                $crg_payout_id = null;
                if (isset($payload['changed_crg_payout_manual']) && (int)$payload['changed_crg_payout_manual'] > 0) {
                    $crg_payout = (int)$payload['changed_crg_payout_manual'];
                } else if (!empty($payload['changed_crg_payout'])) {
                    $deal = explode('_', $payload['changed_crg_payout'], 2);
                    $deal_type = $deal[0];
                    $deal_id = $deal[1] ?? null;
                    if ($deal_type == 'crg') {
                        $b_model = TrafficEndpointPrivateDeal::findOrFail($deal_id);
                        $crg_payout_id = $deal_id;
                        $crg_payout = (int)$b_model->payout;
                    } else if ($deal_type == 'payout') {
                        $b_model = TrafficEndpointPayout::findOrFail($deal_id);
                        $crg_payout = (int)$b_model->payout;
                    } else {
                        throw new \Exception('Unknown deal type: ' . $deal_type);
                    }
                }
                $update = [
                    'changed_crg_percentage_id' => $payload['changed_crg_percentage_id'],
                    'changed_crg_percentage_period' => $endpoint_update['calc_period_crg'],
                    'changed_crg_percentage' => ((int)$endpoint_update['min_crg'] ?? 0),
                    'changed_crg_payout_id' => $crg_payout_id,
                    'changed_crg_payout' => (int)$crg_payout
                ];
            }

            $model->update($update);
        } else {
            $update = [
                'changed_crg_percentage_id',
                'changed_crg_percentage_period',
                'changed_crg_percentage',
                'changed_crg_payout_id',
                'changed_crg_payout'
            ];
            $model->unset($update);
        }

        return true;
    }

    public function get_payout(string $leadId): array
    {
        $data = ['success' => false];
        try {

            $where = array();
            $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);
            $mongo = new MongoDBObjects('leads', $where);
            $find = $mongo->find();

            $data = [
                'id' => $leadId,
                'deposit_revenue' => $find['deposit_revenue'],
                'cost' => $find['cost']
            ];
        } catch (\Exception $ex) {
            $data = ['success' => false];
        }
        return $data;
    }

    public function update_payout(string $leadId, array $payload): bool
    {
        // $data = ['success' => false];
        try {

            $where = array();
            $where['_id'] = new \MongoDB\BSON\ObjectId($leadId);
            $mongo = new MongoDBObjects('leads', $where);

            $update = [
                'deposit_revenue' => $payload['deposit_revenue'],
                'cost' => $payload['cost'],
            ];

            return $mongo->update($update);
            // $data = ['success' => true];
            // }
        } catch (\Exception $ex) {
            // $data = ['success' => false];
        }
        return false;
    }

    public function approve(string $leadId): bool
    {
        $t = new TrafficEndpointRepository();
        $result = $t->approve($leadId);
        return $result['success'];
    }

    private function get_last_lead_by_location(string $country, string $language)
    {
        $where = [
            'country' => strtoupper($country),
            // 'language' => strtolower($language),
            'match_with_broker' => 1,
            'test_lead' => 0,
            'ip' => new \MongoDB\BSON\Regex("\.")
        ];
        $projection = [
            'country' => 1,
            'language' => 1,
            'phone' => 1,
            'short_phone' => 1,
            'ip' => 1,
            'Timestamp' => 1
        ];
        $agregate = [
            'sort' => ['Timestamp' => -1],
            'limit' => 1,
            'project' => $projection
        ];

        $mongo = new MongoDBObjects('leads', $where);
        $find = $mongo->aggregate($agregate, true, false);
        // GeneralHelper::PrintR($find);die();
        // if (!isset($find['ip'])) {
        // 	unset($where['language']);
        // 	$find = $mongo->aggregate($agregate, true, false);
        // }

        if (!isset($find['ip'])) {
            $mongo->without_client_id();
            $find = $mongo->aggregate($agregate, true, false);
        }

        $result = false;
        try {
            if (isset($find['ip'])) {
                $nums = explode(".", $find['ip']);
                $result['ip'] = $nums[0] . '.' . $nums[1] . '.*.*';
            }
        } catch (Exception $ex) {
        }

        try {
            if (!empty($find['phone'])) {
                $find['phone'] = CryptHelper::decrypt($find['phone']);
            }
            if (isset($find['phone']) && strlen($find['phone']) > 5) {
                $pre = substr($find['phone'], 0, strlen($find['phone']) - 3);
                $first2 = substr($pre, 0, 2);

                if ($first2 == '00') {
                    $pre = substr($pre, 2, 100);
                }

                $result['phone'] = $pre . '***';
            }
        } catch (Exception $ex) {
        }

        return $result;
    }

    public function test_lead_data(array $payload): array
    {
        $clientId = ClientHelper::clientId();
        $test_lead_config = config('clients.' . $clientId . '.test-lead');
        if (empty($test_lead_config)) {
            $test_lead_config = config('test-lead') ?? [];
        }

        if (($payload['funnel_language'] ?? '') == 'general') {
            unset($payload['funnel_language']);
        }
        $country = $payload['country'] ?? $test_lead_config['default_country'] ?? 'br';
        $language = $payload['funnel_language'] ?? $test_lead_config['default_language'] ?? 'pt';

        $country_data = $test_lead_config['country_data'][$country . '_' . $language] ?? [];

        if (count($country_data) == 0) {
            $country_data = $test_lead_config['country_data'][$country] ?? [];
            if (isset($country_data['lang'])) {
                $language = $payload['funnel_language'] ?? $country_data['lang'];
            }
        }

        if (
            count($country_data) == 0 ||
            (count($country_data) > 0 &&
                (empty($country_data['lang']) ||
                    empty($country_data['ip']) ||
                    empty($country_data['phone'])
                )
            )
        ) {
            $last_lead = $this->get_last_lead_by_location($country, $language);
            // GeneralHelper::PrintR($last_lead);die();
            if ($last_lead) {
                if (empty($country_data['ip']) && isset($last_lead['ip'])) {
                    $country_data['ip'] = $last_lead['ip'];
                }
                if (empty($country_data['phone']) && isset($last_lead['phone'])) {
                    $country_data['phone'] = $last_lead['phone'];
                }
                if (empty($country_data['lang']) && isset($last_lead['language'])) {
                    $language = $last_lead['language'];
                }
            }
        }

        $country_data_func = fn (string $phone) => preg_replace_callback('#\*#', fn () => (string)random_int(0, 9), $phone);

        $email_func = fn ()  => 'test-' . ((string)time()) . ((string)random_int(1000, 10000)) . '@gmail.com';

        $result = [
            'integrationId' => $payload['integrationId'] ?? '',
            'brokerId' => $payload['brokerId'] ?? '',
            'endpointId' => $payload['endpointId'] ?? $test_lead_config['default_traffic_endpoint'],
            'first_name' => $payload['first_name'] ?? 'test',
            'last_name' => $payload['last_name'] ?? 'test',
            'funnel_lp' => $payload['funnel_lp'] ?? 'test.com',
            'sub_publisher' => $payload['sub_publisher'] ?? '',
            'country' => $country,
            'password' => $payload['password'] ?? 'Ab123456',
            'funnel_language' => $language,
            'email' => $email_func(),
            'phone' => $country_data_func($country_data['phone'] ?? ''),
            'ip' => $country_data_func($country_data['ip'] ?? '')
        ];

        return $result;
    }

    public function test_lead_send(array $payload): array
    {
        $test_lead_config = config('test-lead') ?? [];
        $client_config = ClientHelper::clientConfig();
        $client_id = ClientHelper::clientId();
        $url = $client_config['serving_domain'] . $test_lead_config['url_path'];

        $hardcode_domains = [
            '633c07530b1a55629a3b0a1d' => 'https://leadpoint.jimmywho.media',
            '633c07530b1a55629a3b0a1e' => 'https://lead.apipromoted.com',
            '638f48ad134eb5554c518367' => 'https://lead.apileadmarket.com',
            '63a962b58510e4582848bea1' => 'https://lead.apimarketing.media',
        ];
        
        if (!empty($hardcode_domains[$client_id])) {
            $url = $hardcode_domains[$client_id] . $test_lead_config['url_path'];
        } else {
            $url = $client_config['serving_domain'] . '/api/v1/lead';
        }
        
        $url = $url . '?client=' . $client_id;

        $endpoint = TrafficEndpoint::findOrFail($payload['endpointId'])->toArray(); //traffic_endpoint

        $ch = curl_init();
        $httperror = '';
        $server_output = '';
        $httpcode = 0;

        try {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 400);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "accept: application/json",
                'secret: ' . $endpoint['_id'],
                'apikey: ' . $endpoint['api_key'],
                'password: ' . $endpoint['account_password'],
            ]);

            $server_output = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $httperror = curl_error($ch);
            } else if ($server_output === false) {
                $httperror = "CURL Error: " . curl_error($ch);
            }
        } finally {
            curl_close($ch);
        }

        $server_output_data = null;
        $leadLog = null;
        if (!empty($server_output)) {
            $server_output_data = json_decode($server_output, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                $httperror = 'Unable to parse response body into JSON: ' . json_last_error();
            } else if (!empty($server_output_data['leadid'])) {
                $log = new InvestigateRepository();
                $leadLog = $log->logs($server_output_data['leadid']);
            } else if (!empty($server_output_data['requestid'])) {
                $mongo = new MongoDBObjects('lead_requests', ['token' => $server_output_data['requestid']]);
                $find = $mongo->find();
                if (isset($find) && isset($find['lead_id'])) {
                    $log = new InvestigateRepository();
                    $leadLog = $log->logs($find['lead_id']);
                }
            }
        }
        $data = [
            'success' => $httpcode == 200,
            'httpcode' => $httpcode,
            'data' => $server_output_data,
            'output' => $server_output
        ];

        if ($leadLog != null) {
            $data['logs'] = $leadLog;
        }

        if (!empty($httperror)) {
            $data['success'] = false;
            $data['error'] = $httperror;
        }

        return $data;
    }

    private function group_lead_cost(?Collection $data, string $group_country_field, string $group_language_field, string $value_field): array
    {
        return array_reduce($data ? $data->toArray() : [], function (?array $carry, array $item) use ($group_country_field, $group_language_field, $value_field) {
            $carry ??= [];
            $countries = is_string($item[$group_country_field] ?? null) ? [$item[$group_country_field]] : (array)($item[$group_country_field] ?? []);
            $languages = is_string($item[$group_language_field] ?? null) ? [$item[$group_language_field]] : (array)($item[$group_language_field] ?? []);
            foreach ($countries as $country) {
                $c = $country . '_';
                if (count($languages) > 0) {
                    foreach ($languages as $language) {
                        $carry[$c . $language] = $item[$value_field] ?? 0;
                    }
                } else {
                    $carry[$c] = $item[$value_field] ?? 0;
                }
            }
            return $carry;
        }) ?? [];
    }

    private function get_lead_costs(array $lead): array
    {
        $result = [
            'broker_payout' => 0,
            'endpoint_payout' => 0,
            'MasterAffiliate_payout' => 0,
            'master_brand_payout' => 0
        ];

        $country = strtolower(trim($lead['country']));
        $language = strtolower(trim($lead['language']));
        $cl = $country . '_' . $language;
        $c = $country . '_';

        if ((bool)($lead['broker_cpl'] ?? false) == true) {
            $_broker_crg = BrokerCrg::query()
                ->where('broker', '=', $lead['brokerId'])
                ->where('endpoint', '=', $lead['TrafficEndpoint'])
                ->where('country_code', '=', $country)
                ->where('language_code', '=', $language)
                ->whereIn('type', ['4', 4])
                ->whereIn('status', ['1', 1])
                ->get(['country_code', 'language_code', 'payout']);
            $broker_crg = $this->group_lead_cost($_broker_crg, 'country_code', 'language_code', 'payout');

            $payout = $broker_crg[$cl] ?? $broker_crg[$c] ?? 0;

            if ($payout == 0) {
                $_broker_payouts = BrokerPayout::query()
                    ->where('broker', '=', $lead['brokerId'])
                    ->where('country_code', '=', $country)
                    ->where('cost_type', '=', '2')
                    ->where('enabled', '=', true)
                    ->get(['country_code', 'language_code', 'payout']);
                $broker_payouts = $this->group_lead_cost($_broker_payouts, 'country_code', 'language_code', 'payout');
                $payout = $broker_payouts[$cl] ?? $broker_payouts[$c] ?? 0;
            }

            $result['broker_payout'] = $payout;
        }

        if ((bool)($lead['isCPL'] ?? false) == true) {
            $_endpoint_payouts = TrafficEndpointPayout::query()
                ->where('TrafficEndpoint', '=', $lead['TrafficEndpoint'])
                ->where('country_code', '=', $country)
                ->where('cost_type', '=', '2')
                ->where('enabled', '=', true)
                ->get(['country_code', 'language_code', 'payout']);
            $endpoint_payouts = $this->group_lead_cost($_endpoint_payouts, 'country_code', 'language_code', 'payout');
            $result['endpoint_payout'] = $endpoint_payouts[$cl] ?? $endpoint_payouts[$c] ?? 0;
        }

        $master_payouts = [];
        foreach (['isMasterCPL' => 'MasterAffiliate', 'isMasterBrandCPL' => 'master_brand'] as $master_field_enable => $master_field) {
            if ((bool)($lead[$master_field_enable] ?? false) == true && !empty($lead[$master_field] ?? '')) {
                $_master_payout = MasterPayout::query()
                    ->where('master_partner', '=', $lead[$master_field])
                    ->where('country_code', '=', $country)
                    ->where('cost_type', '=', '2')
                    // ->where('enabled', '=', true)
                    ->get(['country_code', 'language_code', 'payout']);

                $master_payouts[$master_field] = $this->group_lead_cost($_master_payout, 'country_code', 'language_code', 'payout');

                $payout = $master_payouts[$master_field][$cl] ?? $master_payouts[$master_field][$c] ?? 0;

                if ($payout == 0) {
                    $master = Master::query()
                        ->where('_id', '=', new \MongoDB\BSON\ObjectId($lead[$master_field]))
                        ->get(['fixed_price_cpl'])
                        ->first();
                    $payout = $master->fixed_price_cpl ?? 0;
                }

                $result[$master_field . '_payout'] = $payout;
            }
        }


        return $result;
    }

    public function get_change_payout_cpl_lead(string $leadId): array
    {
        $lead = Leads::query()->where('_id', '=', new \MongoDB\BSON\ObjectId($leadId))
            ->get([
                'broker_cpl',
                'isCPL',
                'cost',
                'revenue',
                'Master_brand_cost',
                'Mastercost',
                'MasterAffiliate',
                'master_brand',
                'isMasterCPL',
                'isMasterBrandCPL',
                'country',
                'language',
                'brokerId',
                'TrafficEndpoint'
            ]);
        if ($lead) {
            $lead = $lead->first()->toArray();
            $costs = $this->get_lead_costs($lead);


            $fields = [
                'broker_payout' => 'Broker Payout',
                'endpoint_payout' => 'Endpoint Payout',
                'MasterAffiliate_payout' => 'Master Payout',
                'master_brand_payout' => 'Master Brand Payout'
            ];

            $lead['recomended'] = '';
            foreach ($fields as $f => $title) {
                if (($costs[$f] ?? 0) > 0) {
                    $lead['recomended'] .= (!empty($lead['recomended']) ? ', ' : '') . $title . ': $' . $costs[$f];
                }
            }
            if (!empty($lead['recomended'])) {
                $lead['recomended'] = '<div style="color: var(--light-text); font-size: 0.9rem;">' . $lead['recomended'] . '</span>';
            }

            // $lead['recomended_broker'] = 'test for broker';
            // $lead['recomended_endpoint'] = 'test for endpoint';
            return $lead;
        }
        return [];
    }

    public function post_change_payout_cpl_lead(string $leadId, array $payload): bool
    {
        $model = Leads::findOrFail($leadId);
        $lead = (array)$model->toArray() ?? [];
        $data = array_map(fn (array $l) => [
            'cost' => $l['cost'] ?? 0,
            'revenue' => $l['revenue'] ?? 0,
            'Master_brand_cost' => $l['Master_brand_cost'] ?? 0,
            'Mastercost' => $l['Mastercost'] ?? 0,
            'MasterAffiliate' => $l['MasterAffiliate'] ?? 0,
            'broker_cpl_unpaid' => $l['broker_cpl_unpaid'] ?? false,
            'cpl_unpaid' => $l['cpl_unpaid'] ?? false,
            'isCPL' => $l['isCPL'] ?? false,
            'broker_cpl' => $l['broker_cpl'] ?? false
        ], [$lead]);
        $data = $data[0];

        $update_broker = [];
        $update_endpoint = [];

        if ($model->broker_cpl) {
            $update_broker['revenue'] = $payload['revenue'] ?? 0;
            $update_broker['broker_cpl_unpaid'] = $update_broker['revenue'] == 0;

            if ($model->isMasterBrandCPL) {
                $update_broker['Master_brand_cost'] = $payload['Master_brand_cost'] ?? 0;
            }

            if (($update_broker['revenue'] ?? 0) == 0) {
                $update_broker['Master_brand_cost'] = 0;
            }

            $_data = array_reduce(array_keys($update_broker), function (?array $c, string $f) use ($data, $update_broker) {
                $c ??= [];
                if ($update_broker[$f] != $data[$f]) {
                    $c[$f] = $update_broker[$f];
                }
                return $c;
            }) ?? [];

            if (!empty($_data)) {
                $_data['broker_cpl'] = !($update_broker['broker_cpl_unpaid'] ?? false);
                $fields = array_keys($_data);
                $category = 'broker_cpl';
                // $reasons_change_crg = $payload['broker_reason_change2'] ?? $payload['broker_reason_change'] ?? '';
                $reasons_change_crg = $payload['reason_change2'] ?? $payload['reason_change'] ?? '';
                HistoryDB::add(HistoryDBAction::Update, 'leads', $_data, (string)$model->_id, '', $fields, $category, $reasons_change_crg);
            }
        }

        if ($model->isCPL) {
            $update_endpoint['cost'] = $payload['cost'] ?? 0;
            $update_endpoint['cpl_unpaid'] = $update_endpoint['cost'] == 0;

            if ($model->isMasterCPL) {
                $update_endpoint['Mastercost'] = $payload['Mastercost'] ?? 0;
            }

            if (($update_endpoint['cost'] ?? 0) == 0) {
                $update_endpoint['Mastercost'] = 0;
            }

            $_data = array_reduce(array_keys($update_endpoint), function (?array $c, string $f) use ($data, $update_endpoint) {
                $c ??= [];
                if ($update_endpoint[$f] != $data[$f]) {
                    $c[$f] = $update_endpoint[$f];
                }
                return $c;
            }) ?? [];

            if (!empty($_data)) {
                $_data['isCPL'] = !($update_endpoint['cpl_unpaid'] ?? false);
                $fields = array_keys($_data);
                $category = 'cpl';
                $reasons_change_crg = $payload['reason_change2'] ?? $payload['reason_change'] ?? '';
                HistoryDB::add(HistoryDBAction::Update, 'leads', $_data, (string)$model->_id, '', $fields, $category, $reasons_change_crg);
            }
        }

        $update = array_merge($update_broker, $update_endpoint);

        return !empty($update) ? $model->update($update) : true;
    }
}
