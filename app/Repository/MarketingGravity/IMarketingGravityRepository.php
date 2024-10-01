<?php

namespace App\Repository\MarketingGravity;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMarketingGravityRepository extends IRepository
{
	public function leads(string $gravity_type): array;
	public function change_log(int $limit = 20): array;
	public function reject(string $leadId): array;
	public function approve(string $leadId): array;
}
