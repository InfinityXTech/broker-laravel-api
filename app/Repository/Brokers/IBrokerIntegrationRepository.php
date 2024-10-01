<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerIntegrationRepository extends IRepository {
    public function index(array $columns = ['*'], array $relations = []): array;
    public function create(array $payload): ?Model;
}