<?php

namespace App\Repository\Investigate;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IInvestigateRepository extends IRepository
{
	public function logs(string $leadId): array;
}
