<?php

namespace App\Repository\FireFTD;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IFireFTDRepository extends IRepository {
    public function run(array $payload): array;
}