<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerPrivateDealsRepository extends IRepository {
    public function index(array $columns = ['*'], string $brokerId, array $relations = []): array;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
    public function logs(string $modelId): array;
    public function has_leads(string $brokerId, string $id): bool;
}