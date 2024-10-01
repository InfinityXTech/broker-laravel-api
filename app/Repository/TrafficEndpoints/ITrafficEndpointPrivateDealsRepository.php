<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ITrafficEndpointPrivateDealsRepository extends IRepository
{
    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): array;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
    public function logs(string $modelId, $limit = 20): array;
    public function has_leads(string $traffic_endpoint_id, string $id): bool;
}
