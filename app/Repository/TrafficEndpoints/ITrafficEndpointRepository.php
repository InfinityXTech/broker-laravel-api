<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ITrafficEndpointRepository extends IRepository
{
    public function stat_under_review(): array;
    public function index(array $columns = ['*'], array $relations = [], array $payload = []): Collection;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
    public function log(string $modelId, $limit = 20): array;
    public function reset_password(string $trafficEndpointId): array;
    public function offers_access_get(array $columns = ['*'], string $trafficEndpointId, array $relations = []): Collection;
    public function offers_access_update(string $trafficEndpointId, array $payload): bool;
    public function feed_visualization_group_by_fields(string $trafficEndpointId = ''): array;
    public function feed_visualization_get(string $trafficEndpointId, array $payload): array;
    public function lead_analisis(array $payload): array;
    public function un_payable_leads(array $payload): array;
    public function broker_simulator(array $payload): array;
    public function download_price(): string;
    public function download_crgdeals(string $trafficEndpointId): string;
    public function response_tools(array $payload): array;
}
