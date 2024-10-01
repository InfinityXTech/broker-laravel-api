<?php

namespace App\Repository\Notifications;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface INotificationsRepository extends IRepository
{
    public function notifications(bool $with_access = true): array;
}