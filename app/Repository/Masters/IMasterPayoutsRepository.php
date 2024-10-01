<?php

namespace App\Repository\Masters;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMasterPayoutsRepository extends IRepository
{
    public function index(array $columns = ['*'], string $masterId, array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
    public function delete(string $modelId): bool;
}
