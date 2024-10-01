<?php

namespace App\Repository\Advertisers;

use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;

use Illuminate\Support\Facades\Auth;
use App\Models\Advertisers\MarketingAdvertiserPostEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Advertisers\IAdvertiserPostEventsRepository;

class AdvertiserPostEventsRepository extends BaseRepository implements IAdvertiserPostEventsRepository
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
    public function __construct(MarketingAdvertiserPostEvent $model)
    {
        $this->model = $model;
    }

    public function index(string $advertiserId, array $columns = ['*'], array $relations = []): Collection
    {
        $query = $this->model->with($relations)->where('advertiser', '=', $advertiserId);
        return $query->get($columns);
    }

    public function create(array $payload): ?Model
    {
        $payload['created_by'] = Auth::id();

        $var = date("Y-m-d H:i:s");
        $payload['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    public function delete(string $advertiserId): bool
    {
        $model = MarketingAdvertiserPostEvent::findOrFail($advertiserId);
        return $model->delete();
    }
}
