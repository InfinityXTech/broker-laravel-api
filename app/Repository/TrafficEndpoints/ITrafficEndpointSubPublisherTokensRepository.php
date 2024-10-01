<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ITrafficEndpointSubPublisherTokensRepository extends IRepository {
    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): array;
    public function create(array $payload): ?Model;
}