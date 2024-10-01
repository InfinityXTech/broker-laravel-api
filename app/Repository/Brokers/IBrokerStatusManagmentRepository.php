<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerStatusManagmentRepository extends IRepository {
    public function index(array $columns = ['*'], array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
}