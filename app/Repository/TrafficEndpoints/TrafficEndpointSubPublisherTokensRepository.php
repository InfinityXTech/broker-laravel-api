<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

use App\Models\TrafficEndpoints\TrafficEndpointSubPublisherToken;
use App\Repository\BaseRepository;
use App\Repository\TrafficEndpoints\ITrafficEndpointSubPublisherTokensRepository;
use Exception;

class TrafficEndpointSubPublisherTokensRepository extends BaseRepository implements ITrafficEndpointSubPublisherTokensRepository
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
    public function __construct(TrafficEndpointSubPublisherToken $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): array
    {
        return $this->model->with($relations)->where(['traffic_endpoint' => $trafficEndpointId])->get($columns)->toArray();
    }

    private function generateToken(): string
    {
        $charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $token = substr(str_shuffle($charsz), 0, 2);
        return  'SS' . $token . '' . random_int(1, 1000);
    }

    public function create(array $payload): ?Model
    {
        if (TrafficEndpointSubPublisherToken::query()->where('traffic_endpoint', '=', $payload['traffic_endpoint'])->where('sub_publisher', '=', $payload['sub_publisher'])->get()->count() > 0) {
            throw new Exception('There is already has this sub publisher');
        }
        $payload['token'] = $this->generateToken();
        $model = $this->model->create($payload);
        return $model->fresh();
    }
}
