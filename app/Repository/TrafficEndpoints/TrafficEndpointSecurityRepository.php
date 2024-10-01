<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

use App\Models\TrafficEndpoints\TrafficEndpointSecurity;
use App\Repository\BaseRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointSecurityRepository;

class TrafficEndpointSecurityRepository extends BaseRepository implements ITrafficEndpointSecurityRepository
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
    public function __construct(TrafficEndpointSecurity $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): Collection
    {
        return $this->model->with($relations)->where(['trafficendpoint' => $trafficEndpointId])->get($columns);
    }

    public function create(array $payload): ?Model
    {
        $model = $this->model->create($payload);
        return $model->fresh();
    }

}
