<?php

namespace App\Repository\Clients;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IClientRepository extends IRepository {
    public function index(array $columns = ['*'], array $relations = []): Collection;
    public function get(
        string $modelId,
        array $columns = ['*'],
        array $relations = [],
        array $appends = []
    ): ?Model;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
}