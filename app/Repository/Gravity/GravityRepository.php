<?php

namespace App\Repository\Gravity;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use stdClass;

use App\Models\User;
use App\Models\Broker;
use App\Helpers\CryptHelper;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Gravity\IGravityRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointRepository;

class GravityRepository extends BaseRepository implements IGravityRepository
{

	private $gravity_types = [
		1 => 'Auto Approve',
		2 => 'Manual Approve',
		3 => 'Tech Issue',
		4 => 'High Risk Deposits',
		5 => 'Financial Risk'
	];

	public function __construct()
	{
	}

	private function get_endpoint_names()
	{
		$where = [];
		$mongo = new MongoDBObjects('TrafficEndpoints', $where);
		$partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1]]);
		$result = [];
		foreach ($partners as $partner) {
			$result[MongoDBObjects::get_id($partner)] = ($partner['token'] ?? '');
		}
		return $result;
	}

	private function get_broker_names()
	{
		$where = [/*'partner_type' => '1'*/];
		$mongo = new MongoDBObjects('partner', $where);
		$partners = $mongo->findMany(['projection' => ['_id' => 1, 'partner_name' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1]]);
		$result = [];
		foreach ($partners as $partner) {
			$result[MongoDBObjects::get_id($partner)] = GeneralHelper::broker_name($partner);
		}
		return $result;
	}

	private function get_broker_integrations_names()
	{
		$mongo = new MongoDBObjects('broker_integrations', []);
		$find = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1, 'name' => 1]]);
		$integrations = [];
		foreach ($find as $integration) {
			$integrations[MongoDBObjects::get_id($integration)] = GeneralHelper::broker_integration_name($integration); //($integration['name'] ?? '');
		}
		return $integrations;
	}

	private function feed_counts()
	{
		$where = [
			'depositTypeGravity' => ['$exists' => true],
			'depositor' => true,
			'deposit_disapproved' => true,
			'$or' => [
				['deposit_reject' => false],
				['deposit_reject' => ['$type' => 10]],
				['deposit_reject' => ['$exists' => false]]
			]
		];
		$mongo = new MongoDBObjects('leads', $where);
		$list = $mongo->aggregate([
			'group' => [
				'_id' => '$depositTypeGravity',
				'count' => ['$sum' => 1]
			]
		], false, false);
		$result = [];
		foreach ($list as $group) {
			$result[$group['_id']] = $group['count'];
		}
		return $result;
	}

	public function leads(string $gravity_type): array
	{
		$clientId = ClientHelper::clientId();

		$result = Cache::get('gravity_' . $gravity_type . '_' . $clientId);
		if ($result) {
			return $result;
		}

		// $broker_integrations_names = Cache::get('broker_integrations_names_' . $clientId);
		// if (!$broker_integrations_names) {
		$broker_integrations_names = $this->get_broker_integrations_names();
		// Cache::put('broker_integrations_names_' . $clientId, $broker_integrations_names, 60 * 60);
		// }

		$endpoint_names = Cache::get('endpoint_names_' . $clientId);
		if (!$endpoint_names) {
			$endpoint_names = $this->get_endpoint_names();
			// Cache::put('endpoint_names_' . $clientId, $endpoint_names, 60 * 60);
		}

		// $broker_names = Cache::get('broker_names_' . $clientId);
		// if (!$broker_names) {
		$broker_names = $this->get_broker_names();
		// Cache::put('broker_names_' . $clientId, $broker_names, 60 * 60);
		// }

		$where = [
			'depositTypeGravity' => (int)$gravity_type,
			'depositor' => true,
			'clientId' => $clientId,
			'deposit_disapproved' => true,
			'$or' => [
				['deposit_reject' => false],
				['deposit_reject' => ['$type' => 10]],
				['deposit_reject' => ['$exists' => false]]
			]
		];

        if (Gate::allows('traffic_endpoint[is_only_assigned=1]')) {
            $current_user_id = Auth::id();
            $in = [];
            $allow_endpoints = TrafficEndpoint::query()->orWhere('user_id', '=', $current_user_id)->orWhere('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id)->get(['_id']);
            foreach ($allow_endpoints as $endpoint) {
                $in[] = $endpoint->_id;
            }
            if (empty($in)) {
                $in = ['nothing'];
            }
            $where['TrafficEndpoint'] = ['$in' => $in];
        }

        if (Gate::allows('brokers[is_only_assigned=1]')) {
            $current_user_id = Auth::id();
            $in = [];
            $allow_brokers = Broker::query()->orWhere('created_by', '=', $current_user_id)->orWhere('account_manager', '=', $current_user_id)->get(['_id']);
            foreach ($allow_brokers as $broker) {
                $in[] = $broker->_id;
            }
            if (empty($in)) {
                $in = ['nothing'];
            }
            $where['brokerId'] = ['$in' => $in];
        }

		$mongo = new MongoDBObjects('leads', $where);
		$find = $mongo->findMany();

		$result = [];

		foreach ($find as $lead) {

			CryptHelper::decrypt_lead_data_array($lead);

			if ($lead['depositor'] == true) {
				if (!Gate::allows('custom:crm[crm_depositors]')) { // !permissionsManagement::is_active('crm_depositors')) {
					//$status_td = '';
					$lead['broker_status'] = '';
					// $tdback = '';
				}
			}

			// $tdback = '';
			// if ($lead['depositor'] == true) {
			//     if (!permissionsManagement::is_active('crm_depositors')) {
			//         //$status_td = '';
			//         $lead['broker_status'] = '';
			//         $tdback = '';
			//     }
			// } elseif ($lead['match_with_broker'] !== 1) {
			//     //$status_td = 'Mismatch';
			//     $tdback = 'background-color: #fff6f6;';
			// } else {
			//     $tdback = '';
			//     //$status_td = $lead['status'];
			// }

			$blockReasons = [];

			$dt_lead = ((array)$lead['Timestamp'])['milliseconds'] / 1000; //date('Y-m-d H:i:s',
			if ($dt_lead <= strtotime('2023-03-19 00:00:00')) {
				if (($lead['real_ip'] ?? '') != ($lead['ip'] ?? '') && ($lead['userCountryMatch'] ?? true) == false) {
					$blockReasons[] = 'Block Reason: income country and real country is different';
				}
			} else {
				if (isset($lead['blockReasons'])) {
					$blockReasons = $lead['blockReasons'] ?? [];
				} else {
					if (($lead['userCountryMatch'] ?? true) == false) {
						$blockReasons[] = 'Block Reason: income country and real country is different';
					}
				}
			}

			$result[] =
				[
					'_id' => MongoDBObjects::get_id($lead),
					'first_name' => ucfirst($lead['first_name'] ?? ''),
					'last_name' => ucfirst($lead['last_name'] ?? ''),
					"country" => $lead['country'],
					"language" => $lead['language'],
					"email" => $lead['email'],
					"broker" => [
						"_id" => ($lead['brokerId'] ?? ''),
						"name" => ($broker_names[$lead['brokerId'] ?? ''] ?? ''),
					],
					"broker_lead_id" => $lead['broker_lead_id'],
					"integration" => [
						"_id" => ($lead['integrationId'] ?? ''),
						"name" => ($broker_integrations_names[$lead['integrationId'] ?? ''] ?? ''),
					],
					"broker_status" => ($lead['broker_status'] ?? ''),
					"funnel_lp" => ($lead['funnel_lp'] ?? ''),
					"hit_the_redirect" => ($lead['hit_the_redirect'] ?? ''),
					"riskScore" => ($lead['riskScore'] ?? ''),
					"riskScale" => ($lead['riskScale'] ?? ''),
					"endpoint" => [
						"_id" => ($lead['TrafficEndpoint'] ?? ''),
						"name" => ($endpoint_names[$lead['TrafficEndpoint'] ?? ''] ?? ''),
					],
					"timestamp" => $lead['Timestamp'],
					"test_lead" => $lead['test_lead'] ?? 0,
					"match_with_broker" => $lead['match_with_broker'],
					"isCPL" => $lead['isCPL'] ?? false,
					"broker_cpl" => $lead['broker_cpl'] ?? false,
					"crg_deal" => ($lead['crg_deal'] ?? false),
					"crg_percentage_id" => ($lead['crg_percentage_id'] ?? ''),
					"crg_ignored_by_status" => ($lead['crg_ignored_by_status'] ?? false),
					"broker_crg_deal" => ($lead['broker_crg_deal'] ?? false),
					"broker_crg_percentage_id" => ($lead['broker_crg_percentage_id'] ?? ''),
					"broker_crg_ignored_by_status" => ($lead['broker_crg_ignored_by_status'] ?? false),

					'ip' => $lead['ip'] ?? null,
					'real_ip' => $lead['real_ip'] ?? null,
					// 'country' => $lead['country'] ?? null,
					'real_country' => $lead['real_country'] ?? null,
					'userIpMatch' => $lead['userIpMatch'] ?? null,
					'userCountryMatch' => $lead['userCountryMatch'] ?? null,

					'blockReasons' => $blockReasons,
					'depositGravityFinRiskReasons' => $lead['depositGravityFinRiskReasons'] ?? []
				];
		}

		$seconds = 60;
		Cache::put('gravity_' . $gravity_type . '_' . $clientId, $result, $seconds);

		return $result;
	}

	public function change_log(int $page = 1, int $count_in_page = 60): array
	{
		$where = [
			'collection' => 'leads',
			// 'timestamp' => ['$gte' => new \MongoDB\BSON\UTCDateTime(strtotime('-125 days') * 1000)],
			'$or' => [
				// ['diff.deposit_disapproved' => ['$exists' => true]],
				// ['diff.deposit_reject' => ['$exists' => true]],
				['diff.deposit_disapproved' => ['$type' => 8]],
				['diff.deposit_reject' => ['$type' => 8]],
			]
		];
		$mongo = new MongoDBObjects('history', $where);

		$count_cache_key = 'gravity_log_count_' . ClientHelper::clientId();
		$count = Cache::get($count_cache_key);
		if (!$count) {
			$count = $mongo->count();
			Cache::put($count_cache_key, $count, 60 * 60 * 24);
		}

		$list = $mongo->findMany([
			'sort' => ['timestamp' => -1],
			'skip' => (($page - 1) * $count_in_page),
			'limit' => $count_in_page
		]);

		$broker_integrations_names = $this->get_broker_integrations_names();
		$endpoint_names = $this->get_endpoint_names();
		$broker_names = $this->get_broker_names();

		$where = [
			'_id' => ['$in' => array_map(function ($history) {
				return $history['primary_key'];
			}, $list)]
		];
		$mongo = new MongoDBObjects('leads', $where);
		$_leads = $mongo->findMany();
		$leads = [];
		foreach ($_leads as $lead) {
			CryptHelper::decrypt_lead_data_array($lead);
			$leads[MongoDBObjects::get_id($lead)] = $lead;
		}

		$result = [];

		foreach ($list as $history) {
			$dt = date('Y-m-d H:i:s', ((array)$history['timestamp'])['milliseconds'] / 1000);

			$lead_id = (array)$history['primary_key'];
			$lead_id = $lead_id['oid'];

			$lead = $leads[$lead_id];

			$changed_by = (array)$history['action_by'];
			$changed_by = $changed_by['oid'];
			$changed_by = User::query()->find($changed_by)->name;

			$diff = $history['diff'];

			$deposit_rejected = ($diff['deposit_reject'] ?? false) || ($diff['deposit_disapproved'] ?? false);

			$result[] = [
				'_id' => $lead_id, //lead_id
				'email' => $lead['email'],
				'date' => $history['timestamp'],
				'changed_by' => $changed_by,
				'deposit_reject' => !$deposit_rejected, //($diff['deposit_reject'] ?? false),
				'broker' => [
					'_id' => ($lead['brokerId'] ?? ''),
					'name' => ($broker_names[$lead['brokerId'] ?? ''] ?? ''),
				],
				'country' => $lead['country'],
				'language' => $lead['language'],
				'integration' => [
					'_id' => ($lead['integrationId'] ?? ''),
					'name' => ($broker_integrations_names[$lead['integrationId'] ?? ''] ?? '')
				],
				'endpoint' => [
					'_id' => ($lead['TrafficEndpoint'] ?? ''),
					'name' => ($endpoint_names[$lead['TrafficEndpoint'] ?? ''] ?? '')
				]
			];
		}

		return ['count' => $count, 'items' => $result];
	}

	public function reject(string $leadId): array
	{
		$t = new TrafficEndpointRepository();
		return $t->reject($leadId);
	}

	public function approve(string $leadId): array
	{
		$t = new TrafficEndpointRepository();
		return $t->approve($leadId);
	}
}
