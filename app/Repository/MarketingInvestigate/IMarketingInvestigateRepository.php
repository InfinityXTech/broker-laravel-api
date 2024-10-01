<?php

namespace App\Repository\MarketingInvestigate;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMarketingInvestigateRepository extends IRepository
{
	public function logs(string $clickId): array;
}
