<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerRepository extends IRepository {
    public function index(array $columns = ['*'], array $relations = []): array;
    public function create(array $payload): ?Model;
    public function un_payable_leads(array $payload): array;
    public function conversion_rates($modelId): array;
    public function download_price(): string;
    public function download_crgdeals(string $broker_id): string;
}
