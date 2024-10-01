<?php

namespace App\Repository\Campaigns;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use App\Classes\History\HistoryDiff;

use App\Models\Campaigns\MarketingCampaignTargetingLocation;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Campaigns\ICampaignTargetingLocationsRepository;

class CampaignTargetingLocationsRepository extends BaseRepository implements ICampaignTargetingLocationsRepository
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
    public function __construct(MarketingCampaignTargetingLocation $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], string $campaignId, array $relations = []): Collection
    {
        return $this->model->with($relations)->where(['campaign' => $campaignId])->get($columns);
    }

    public function create(array $payload): ?Model
    {
        $query = MarketingCampaignTargetingLocation::query()
            ->where('campaign', '=', $payload['campaign'])
            ->where('country_code', '=', $payload['country_code']);

        // $query = $query->where(function ($q)  use ($payload) {
        //     $q->orWhere('language_code', '=', ($payload['language_code'] ?? ''))->orWhere('language_code', '=', ($payload['language_code'] ?? null));
        // });

        $count = $query->get()->count();

        if ($count > 0) {
            throw new \Exception('There is already exists such a country');
        }

        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function update(string $modelId, array $payload): bool
    {
        $model = $this->findById($modelId);       
        return $model->update($payload);
    }

}
