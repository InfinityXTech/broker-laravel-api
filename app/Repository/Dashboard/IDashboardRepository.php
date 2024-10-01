<?php

namespace App\Repository\Dashboard;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IDashboardRepository extends IRepository {
    public function index(): array;
}