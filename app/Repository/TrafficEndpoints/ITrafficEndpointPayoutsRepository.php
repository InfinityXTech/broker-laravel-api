<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ITrafficEndpointPayoutsRepository extends IRepository
{
    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
    public function delete(string $modelId): bool;
    public function log(string $payoutId, $limit = 20): array;
}
