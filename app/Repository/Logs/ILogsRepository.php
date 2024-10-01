<?php

namespace App\Repository\Logs;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ILogsRepository extends IRepository
{
    public function get_log(int $page): array;
}
