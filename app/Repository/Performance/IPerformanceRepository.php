<?php

namespace App\Repository\Performance;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IPerformanceRepository extends IRepository
{
    public function general(array $payload): array;
    public function traffic_endpoints(array $payload): array;
    public function brokers(array $payload): array;
    public function vendors(array $payload): array;
    public function deep_dive(array $payload): array;
    public function download(array $payload): string;
    public function settings_broker_statuses_all(): array;
    public function settings_broker_statuses_get(string $id): array;
    public function settings_broker_statuses_delete(string $id): bool;
    public function settings_broker_statuses_create(array $payload): bool;
    public function settings_broker_statuses_update(string $id, array $payload): bool;
}
