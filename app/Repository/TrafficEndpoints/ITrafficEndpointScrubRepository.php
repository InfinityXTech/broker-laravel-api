<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ITrafficEndpointScrubRepository extends IRepository {
    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): Collection;
    public function create(array $payload): ?Model;
}