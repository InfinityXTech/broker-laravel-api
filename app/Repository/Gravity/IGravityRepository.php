<?php

namespace App\Repository\Gravity;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IGravityRepository extends IRepository
{
	public function leads(string $gravity_type): array;
	public function change_log(int $page = 1, int $count_in_page = 60): array;
	public function reject(string $leadId): array;
	public function approve(string $leadId): array;
}
