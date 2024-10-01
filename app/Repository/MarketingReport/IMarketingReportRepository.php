<?php

namespace App\Repository\MarketingReport;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMarketingReportRepository extends IRepository
{
    public function run(array $payload): array;
    public function download(array $payload): array;
    public function pivot(): array;
    public function metrics(): array;
}
