<?php

namespace App\Repository\MarketingBillings;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMarketingBillingsRepository extends IRepository {
    public function overall(): array;
    public function pending_payments(): array;
    public function advertisers_balances(array $payload): array;
    public function affiliates_balances(array $payload): array;
    public function approved(array $payload): array;
}