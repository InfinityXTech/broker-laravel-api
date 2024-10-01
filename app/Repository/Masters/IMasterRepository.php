<?php

namespace App\Repository\Masters;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMasterRepository extends IRepository
{
    public function index(array $columns = ['*'], array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function reset_password(string $masterId): array;
}
