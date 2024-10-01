<?php

namespace App\Repository\QualityReport;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IQualityReportRepository extends IRepository {
    public function run(array $payload): array;
    public function pivot(): array;
    public function metrics(): array;
    public function download(array $payload): array;
}