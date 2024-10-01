<?php

namespace App\Repository\Brokers;

use App\Models\Broker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Brokers\BrokerStatus;
use App\Repository\BaseRepository;
use App\Repository\Brokers\IBrokerStatusManagmentRepository;

class BrokerStatusManagmentRepository extends BaseRepository implements IBrokerStatusManagmentRepository
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
    public function __construct(BrokerStatus $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): Collection
    {
        $items = BrokerStatus::where($relations)->get($columns);
        return $items;
    }

    public function create(array $payload): ?Model
    {
        $exists = BrokerStatus::where('broker', '=', $payload['broker'])->where('broker_status', $payload['broker_status'])->first();
        if ($exists) {
            throw new \Exception('Broker status already exists: ' . $payload['broker_status']);
        }
        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function update(string $modelId, array $payload): bool
    {
        $exists = BrokerStatus::query()->where('broker', '=', $modelId)->where('broker_status', $payload['broker_status'])->first();
        if ($exists) {
            throw new \Exception('Broker status already exists: ' . $payload['broker_status']);
        }
        $model = BrokerStatus::findOrFail($modelId);
        $model = $model->update($payload);
        return $model->fresh();
    }
}
