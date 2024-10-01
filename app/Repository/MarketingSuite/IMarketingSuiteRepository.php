<?php

namespace App\Repository\MarketingSuite;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMarketingSuiteRepository extends IRepository {
    public function index(array $columns = ['*'], array $relations = []): Collection;
    public function get(string $modelId): ?Model;
    public function create(array $payload): ?Model;
    public function update(string $modelId, array $payload): bool;
    public function get_tracking_link(string $modelId): string;
}