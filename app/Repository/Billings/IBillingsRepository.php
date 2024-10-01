<?php

namespace App\Repository\Billings;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBillingsRepository extends IRepository {
    public function overall(): array;
    public function pending_payments(): array;
    public function brokers_balances(array $payload): array;
    public function endpoint_balances(array $payload): array;
    public function approved(array $payload): array;
}