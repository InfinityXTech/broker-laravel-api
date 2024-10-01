<?php

namespace App\Repository\MarketingInvestigate;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use stdClass;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Models\MLeads;
use App\Models\MLeadsEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\MarketingInvestigate\IMarketingInvestigateRepository;

class MarketingInvestigateRepository extends BaseRepository implements IMarketingInvestigateRepository
{
	public function __construct()
	{
	}

	private function getClickData(string $clickId): array
	{
		return MLeads::query()
			->with([
				'advertiser_data:name,token',
				'affiliate_data:name,token',
				'campaign_data:name,token'
			])
			->where('ClickID', '=', $clickId)
			->where('EventType', '=', 'CLICK')
			->get()
			->first()
			->toArray();
	}

	private function getConversionData(string $clickId): array
	{
		return MLeads::query()
			->with([
				'advertiser_data:name,token',
				'affiliate_data:name,token',
				'campaign_data:name,token'
			])
			->where('ClickID', '=', $clickId)
			->where('EventType', '!=', 'CLICK')
			->get()
			->toArray();
	}

	private function getEventData(string $clickId): array
	{
		return MLeadsEvent::query()
			->with([
				'advertiser_data:name,token',
				'affiliate_data:name,token',
				'campaign_data:name,token'
			])
			->where('ClickID', '=', $clickId)
			->get()
			->toArray();
	}

	public function logs(string $clickId): array
	{
		$clickData = $this->getClickData($clickId);
		$conversionData = $this->getConversionData($clickId);
		$eventsData = $this->getEventData($clickId);

		$skips = [];
		$err = (array)($clickData['skips'] ?? $clickData['error_log'] ?? []);
		foreach ($err as $campaignId => $skip) {
			if (!empty($skip)) {
				$skips[$campaignId] ??= [];
				$skips[$campaignId][] = [
					'advertiser_data' => $clickData['advertiser_data'],
					'affiliate_data' => $clickData['affiliate_data'],
					'campaign_data' => $clickData['campaign_data'],
					'success' => false,
					'title' => 'Click Error',
					'content' => $skip
				];
			}
		}

		$result = [
			'user_data' => [$clickData],
			'event_data' => array_merge($eventsData, $conversionData),
			'redirect_data' => [
				['key' => 'Tracking Link', 'value' => $clickData['CampaignTrackingLink'] ?? '']
			],
			'log_data' => $skips
		];
		return $result;
	}
}
