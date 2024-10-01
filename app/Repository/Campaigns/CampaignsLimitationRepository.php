<?php

namespace App\Repository\Campaigns;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use App\Classes\History\HistoryDiff;

use App\Models\Campaigns\MarketingCampaignLimitationEndpoints;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Campaigns\ICampaignsLimitationRepository;

class CampaignsLimitationRepository extends BaseRepository implements ICampaignsLimitationRepository
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
    public function __construct(MarketingCampaignLimitationEndpoints $model)
    {
        $this->model = $model;
    }

    public function sub_publishers(array $columns = ['*'], string $campaignId, array $relations = []): Collection
    {
        return $this->model->with($relations)->where(['campaign' => $campaignId])->get($columns);
    }

    public function sub_publishers_create(array $payload): ?Model
    {
        // $query = MarketingCampaignPayout::query()
        //     ->where('campaign', '=', $payload['campaign'])
        //     ->where('traffic_endpoint', '=', $payload['country_code']);

        // $count = $query->get()->count();

        // if ($count > 0) {
        //     throw new \Exception('There is already exists such a country');
        // }

        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function sub_publishers_update(string $subPublisherId, array $payload): bool
    {
        $model = $this->findById($subPublisherId);
        return $model->update($payload);
    }

    public function sub_publishers_delete(string $subPublisherId): bool {
        $model = $this->findById($subPublisherId);
        return $model->delete();
    }

}
