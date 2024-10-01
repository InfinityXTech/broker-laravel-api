<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerCapsRepository extends IRepository {
    public function index(?array $filter = null): array;
    public function force_update(string $modelId, array $payload): bool;
    public function cap_countries(string $brokerId): string;
    public function logs(string $capId): array;
    public function available_endpoints(string $capId): array;
}