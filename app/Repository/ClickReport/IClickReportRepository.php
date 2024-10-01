<?php

namespace App\Repository\ClickReport;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IClickReportRepository extends IRepository {
    public function run(array $payload): array;
    public function pivot(): array;
    public function metrics(): array;
    public function download(array $payload): array;
}