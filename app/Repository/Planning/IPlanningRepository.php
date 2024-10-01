<?php

namespace App\Repository\Planning;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IPlanningRepository extends IRepository {
    public function run(array $payload): array;
    public function get_countries_and_languages(array $payload): array;
}