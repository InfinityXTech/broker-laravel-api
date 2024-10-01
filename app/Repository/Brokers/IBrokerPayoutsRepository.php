<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerPayoutsRepository extends IRepository {
    public function index(array $columns = ['*'], string $brokerId, array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function log(string $payoutId, $limit = 20): array;
}