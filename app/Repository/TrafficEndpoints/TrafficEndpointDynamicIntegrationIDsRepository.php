<?php

namespace App\Repository\TrafficEndpoints;

use App\Helpers\GeneralHelper;

use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Models\TrafficEndpoints\TrafficEndpointDynamicIntegrationIDs;
use App\Repository\TrafficEndpoints\ITrafficEndpointDynamicIntegrationIDsRepository;

class TrafficEndpointDynamicIntegrationIDsRepository extends BaseRepository implements ITrafficEndpointDynamicIntegrationIDsRepository
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
    public function __construct(TrafficEndpointDynamicIntegrationIDs $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): Collection
    {
        $items = $this->model->with($relations)->where(['TrafficEndpoint' => $trafficEndpointId])->get($columns)->toArray();
        return new \Illuminate\Database\Eloquent\Collection($items);
    }

    public function create(array $payload): ?Model
    {
        $query = TrafficEndpointDynamicIntegrationIDs::query()
            ->where('TrafficEndpoint', '=', $payload['TrafficEndpoint'])
            ->where('brokerId', '=', $payload['brokerId'])
            ->where('integrationId', '=', $payload['integrationId']);

        $count = $query->get()->count();

        if ($count > 0) {
            throw new \Exception('Broker with Integration is already exist');
        }

        $payload['status'] = strval($payload['status'] ?? '0');
        $payload['created_by'] = Auth::id();

        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function update(string $modelId, array $payload): bool
    {
        $model = TrafficEndpointDynamicIntegrationIDs::findOrFail($modelId);
        $payload['status'] = strval($payload['status'] ?? '0');
        return $model->update($payload);
    }

    public function delete(string $modelId): bool
    {
        $model = TrafficEndpointDynamicIntegrationIDs::findOrFail($modelId);
        return $model->delete($modelId);
    }
}
