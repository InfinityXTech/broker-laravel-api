<?php

namespace App\Repository\Campaigns;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Models\MarketingCampaign;
use App\Repository\BaseRepository;

use App\Classes\History\HistoryDiff;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Campaigns\MarketingCampaignPayout;
use App\Repository\Campaigns\ICampaignBudgetRepository;
use App\Models\Campaigns\MarketingCampaignEndpointAllocations;
use Exception;

class CampaignBudgetRepository extends BaseRepository implements ICampaignBudgetRepository
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(MarketingCampaignEndpointAllocations $model)
    {
        $this->model = $model;
    }

    private function check_limit(string $campaignId, float $cap, string $allocationId = '')
    {
        $campaign = MarketingCampaign::findOrFail($campaignId);
        
        if ((float)($campaign->daily_cap ?? -1) < 0) {
            return true;
        }
        
        $all_cap = 0;

        $query = MarketingCampaignEndpointAllocations::query()->where('campaign', '=', $campaignId);
        if (!empty($allocationId)) {
            $query = $query->where('_id', '!=', $allocationId);
        }

        $caps = $query->get(['daily_cap']);

        foreach ($caps as $_cap) {
            $all_cap += (float)$_cap->daily_cap ?? 0;
        }

        // GeneralHelper::PrintR(((float)$cap + (float)$all_cap));die();
        if ((float)$campaign->daily_cap < ((float)$cap + (float)$all_cap)) {
            throw new Exception('You have exceeded the total limit Daily Cap');
        }
    }

    public function endpoint_allocations(array $columns = ['*'], string $campaignId, array $relations = []): Collection
    {
        return $this->model->with($relations)->where(['campaign' => $campaignId])->get($columns);
    }

    public function endpoint_allocation_create(array $payload): ?Model
    {
        $query = MarketingCampaignEndpointAllocations::query()
            ->where('campaign', '=', $payload['campaign'])
            ->where('affiliate', '=', $payload['affiliate'])
            ->where('country_code', '=', $payload['country_code']);

        // $query = $query->where(function ($q)  use ($payload) {
        //     $q->orWhere('language_code', '=', ($payload['language_code'] ?? ''))->orWhere('language_code', '=', ($payload['language_code'] ?? null));
        // });

        $count = $query->get()->count();

        if ($count > 0) {
            throw new \Exception('There is already exists such a traffic endpoint and country');
        }

        $this->check_limit($payload['campaign'], $payload['daily_cap']);

        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function endpoint_allocation_update(string $allocationId, array $payload): ?Model
    {
        $model = $this->findById($allocationId);

        foreach ($payload as $key => $value) {
            $model->{$key} = $value;
        };

        // $diff = [];
        // foreach ($payload as $key => $value) {
        //     if ($key != 'description' && $model->$key != $value) {
        //         $diff[$key . '_pre'] = $model->$key;
        //         $diff[$key] = $value;
        //     }
        // }
        // if (!empty($diff)) {
        //     $diff['action'] = 'UPDATE';
        //     $diff['broker'] = $model->broker;
        //     $diff['description'] = $payload['description'] ?? null;
        //     $diff['action_by'] = GeneralHelper::get_current_user_token();
        //     $diff['timestamp'] = GeneralHelper::ToMongoDateTime(time());
        //     BrokerPayoutLog::create($diff);
        // }

        $this->check_limit($model->campaign, $model->daily_cap, $allocationId);

        $model->update($payload);

        return $model->fresh();
    }

    public function endpoint_allocation_delete(string $allocationId): bool
    {
        $model = MarketingCampaignPayout::findorFail($allocationId);
        return $model->delete();
    }
}
