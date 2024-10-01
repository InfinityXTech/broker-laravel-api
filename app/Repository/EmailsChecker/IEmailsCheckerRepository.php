<?php

namespace App\Repository\EmailsChecker;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IEmailsCheckerRepository extends IRepository {
    public function run(array $payload): string;
}