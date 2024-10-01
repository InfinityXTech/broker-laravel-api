<?php

namespace App\Repository\MarketingGravity;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use stdClass;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\ClientHelper;
use App\Models\MarketingAdvertiser;
use App\Models\MarketingAffiliate;
use App\Models\MarketingCampaign;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\MarketingGravity\IMarketingGravityRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointRepository;

class MarketingGravityRepository extends BaseRepository implements IMarketingGravityRepository
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

	private function get_affiliate_names()
	{
		$affiliates = MarketingAffiliate::all(['_id', 'token']);
		$result = [];
		foreach ($affiliates as $aff) {
			$result[$aff->_id] = ($aff->token ?? '');
		}
		return $result;
	}

	private function get_advertiser_names()
	{
		$advertisers = MarketingAdvertiser::all(['_id', 'token']);
		$result = [];
		foreach ($advertisers as $adv) {
			$result[$adv->_id] = ($adv->token ?? '');
		}
		return $result;
	}

	private function get_campaign_names()
	{
		$advertisers = MarketingCampaign::all(['_id', 'token', 'name']);
		$result = [];
		foreach ($advertisers as $adv) {
			$result[$adv->_id] = ($adv->token ?? '');
		}
		return $result;
	}

	private function feed_counts()
	{
		$where = [
			'TypeGravity' => ['$exists' => true],
			'Approved' => true,
			'$or' => [
				['Rejected' => false],
				['Rejected' => ['$type' => 10]],
				['Rejected' => ['$exists' => false]]
			]
		];
		$mongo = new MongoDBObjects('mleads', $where);
		$list = $mongo->aggregate([
			'group' => [
				'_id' => '$TypeGravity',
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

		$result = null;//Cache::get('marketing_gravity_' . $gravity_type . '_' . $clientId);
		if ($result) {
			return $result;
		}

		$affiliate_names = Cache::get('affiliate_names_' . $clientId);
		if (!$affiliate_names) {
			$affiliate_names = $this->get_affiliate_names();
			Cache::put('affiliate_names_' . $clientId, $affiliate_names, 60 * 60);
		}

		$advertiser_names = Cache::get('advertiser_names_' . $clientId);
		if (!$advertiser_names) {
			$advertiser_names = $this->get_advertiser_names();
			Cache::put('advertiser_names_' . $clientId, $advertiser_names, 60 * 60);
		}

		$campaign_names = $this->get_campaign_names();

		$where = [
			'TypeGravity' => (int)$gravity_type,
			'clientId' => $clientId,
			'Approved' => false,
			'$or' => [
				['Rejected' => false],
				['Rejected' => ['$type' => 10]],
				['Rejected' => ['$exists' => false]]
			]
		];

		$mongo = new MongoDBObjects('mleads', $where);
		$find = $mongo->findMany();

		$result = [];

		foreach ($find as $lead) {

			// if ($lead['depositor'] == true) {
			// 	if (!Gate::allows('custom:crm[crm_depositors]')) { // !permissionsManagement::is_active('crm_depositors')) {
			// 		$lead['broker_status'] = '';
			// 	}
			// }

			$result[] =
				[
					'_id' => MongoDBObjects::get_id($lead),
					// 'first_name' => $lead['first_name'],
					// 'last_name' => $lead['last_name'],
					'click_id' => ($lead['ClickID'] ?? ''),
					"country" => $lead['GeoCountryName'],
					"language" => $lead['UserLanguage'],
					"email" => '',//$lead['email'],
					"advertiser" => [
						"_id" => ($lead['AdvertiserId'] ?? ''),
						"name" => ($advertiser_names[$lead['AdvertiserId'] ?? ''] ?? ''),
					],
					"affiliate" => [
						"_id" => ($lead['AffiliateId'] ?? ''),
						"name" => ($affiliate_names[$lead['AffiliateId'] ?? ''] ?? ''),
					],
					"campaign" => [
						"_id" => ($lead['CampaignId'] ?? ''),
						"name" => ($campaign_names[$lead['CampaignId'] ?? ''] ?? ''),
					],
					// "funnel_lp" => ($lead['funnel_lp'] ?? ''),
					// "hit_the_redirect" => ($lead['hit_the_redirect'] ?? ''),
					"riskScore" => ($lead['riskScore'] ?? ''),
					"riskScale" => ($lead['riskScale'] ?? ''),
					"timestamp" => $lead['EventTimeStamp']
				];
		}

		$seconds = 60;
		Cache::put('marketing_gravity_' . $gravity_type . '_' . $clientId, $result, $seconds);

		return $result;
	}

	public function change_log(int $limit = 20): array
	{
		$where = [
			'collection' => 'mleads',
			'timestamp' => ['$gte' => new \MongoDB\BSON\UTCDateTime(strtotime('-7 days') * 1000)],
			'$or' => [
				['diff.Approved' => ['$exists' => true]],
				['diff.Rejected' => ['$exists' => true]],
			]
		];
		$mongo = new MongoDBObjects('history', $where);
		$list = $mongo->findMany([
			'sort' => ['timestamp' => -1],
			'limit' => $limit
		]);

		$affiliate_names = $this->get_affiliate_names();
		$advertiser_names = $this->get_advertiser_names();
		$campaign_names = $this->get_campaign_names();

		$where = [
			'_id' => ['$in' => array_map(function ($history) {
				return $history['primary_key'];
			}, $list)]
		];
		$mongo = new MongoDBObjects('mleads', $where);
		$_leads = $mongo->findMany();
		$leads = [];
		foreach ($_leads as $lead) {
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

			$Rejected = ($diff['Rejected'] ?? false) || ($diff['Approved'] ?? false);

			$result[] = [
				'lead_id' => $lead_id,
				'click_id' => ($lead['ClickID'] ?? ''),
				'date' => $history['timestamp'],
				'changed_by' => $changed_by,
				'Rejected' => !$Rejected, //($diff['Rejected'] ?? false),
				'advertiser' => [
					'_id' => ($lead['AdvertiserId'] ?? ''),
					'name' => ($advertiser_names[$lead['AdvertiserId'] ?? ''] ?? ''),
				],
				'affiliate' => [
					'_id' => ($lead['AffiliateId'] ?? ''),
					'name' => ($affiliate_names[$lead['AffiliateId'] ?? ''] ?? '')
				],
				'campaign' => [
					'_id' => ($lead['CampaignId'] ?? ''),
					'name' => ($campaign_names[$lead['CampaignId'] ?? ''] ?? '')
				],
				'country' => strtolower($lead['GeoCountryName']),
			];
		}

		return $result;
	}

	public function postbackFTD($lead)
	{

		if (!isset($lead)) return;

		$affiliate = MarketingAffiliate::query()->find($lead['AffiliateId'])->get();

		if (!isset($affiliate)) return;

		if (!isset($endpoint)) return;

		if (isset($endpoint['postback']) && !empty($endpoint['postback'])) {

			$find = array();
			$find[] = '{lead_id}';
			$find[] = '{payout}';
			$find[] = '{clickid}';

			$id = (array)$lead['_id'];
			$lead_id = $id['oid'];

			$replace = array();
			$replace[] = $lead_id;

			// if ($lead['isCPL'] == true) {
			// 	$replace[] = 0;
			// } else {
			$replace[] = $lead['AffiliatePayout'];
			// }
			$replace[] = $lead['publisher_click'];
			$url = str_ireplace($find, $replace, $endpoint['postback']);

			if (isset($url) && !empty($url)) {
				file_get_contents($url);
			}
		}
	}

	public function reject(string $leadId): array
	{
		$where = ['_id' => new \MongoDB\BSON\ObjectId($leadId)];
		$mongo = new MongoDBObjects('mleads', $where);
		$lead = $mongo->find();

		$clientId = $lead['clientId'] ?? '';
		$gravity_type = $lead['EventTypeGravity'] ?? '';
		Cache::forget('marketing_gravity_' . $gravity_type . '_' . $clientId);

		$update = array();
		$update['Approved'] = false;
		$update['Rejected'] = true;

		$var = date("Y-m-d H:i:s");
		$update['EventTimeStamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

		if ($mongo->update($update)) {
			$data = array();
			$data['success'] = true;
		} else {
			$data = array();
			$data['success'] = false;
		}

		return $data;
	}

	private function getInHouseTrafficEndpoint(string $clientId): string
    {
        $inHouseTrafficEndpoint = '';

        if (empty($inHouseTrafficEndpoint)) {
            $where = [
                'clientId' => $clientId,
                'in_house' => true
            ];
            $mongo = new MongoDBObjects('TrafficEndpoints', $where);
            $find = $mongo->find(['_id' => 1]);
            if (isset($find)) {
                $inHouseTrafficEndpoint = MongoDBObjects::get_id($find);
            }
        }

        return $inHouseTrafficEndpoint;
    }

	private function updateLeadCostInHouse(array $leads): void
    {
        foreach ($leads as $lead) {

            $clientId = $lead['clientId'] ?? '';
            $clickId = $lead['ClickID'] ?? '';
            $type_event = $lead['EventTypeSchema'] ?? '';
            $cost = $lead['AffiliatePayout'] ?? 0;

            if ($cost > 0 && !empty($clickId) && !empty($clientId)) {

                $inHouseTrafficEndpoint = $this->getInHouseTrafficEndpoint($clientId);

                if (!empty($inHouseTrafficEndpoint)) {
                    $where = [
                        'clientId' => $clientId,
                        'publisher_click' => $clickId,
                        'match_with_broker' => 1,
                        'TrafficEndpoint' => $inHouseTrafficEndpoint
                    ];

                    $mongo = new MongoDBObjects('leads', $where);
                    $update = ['cost' => $cost];
                    switch (strtoupper($type_event)) {
                        case 'CPL': {
                                $update['isCPL'] = true;
                                break;
                            }
                        case 'CPA': {
                                $update['isCPL'] = false;
                                $update['endpointDepositTimestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000);
                                break;
                            }
                    }

                    if (!empty($update)) {
                        $mongo->update($update);
                    }
                }
            }
        }
    }

	public function approve(string $leadId): array
	{
		$where = ['_id' => new \MongoDB\BSON\ObjectId($leadId)];
		$mongo = new MongoDBObjects('mleads', $where);
		$lead = $mongo->find();

		$gravity_type = $lead['EventTypeGravity'] ?? '';
		$clientId = $lead['clientId'] ?? '';

		Cache::forget('marketing_gravity_' . $gravity_type . '_' . $clientId);

		$update = array();

		$update['Rejected'] = false;
		$update['Approved'] = true;

		$var = date("Y-m-d H:i:s");
		if (!isset($lead['EventTimeStamp']) || empty($lead['EventTimeStamp'])) {
			$update['EventTimeStamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
		}

		if ($mongo->update($update)) {

			$this->updateLeadCostInHouse([$lead]);
			$this->postbackFTD($lead);
			// $this->metricFTDs([$lead]);

			$data = array();
			$data['success'] = true;
		} else {
			$data = array();
			$data['success'] = false;
		}
		return $data;
	}
}
