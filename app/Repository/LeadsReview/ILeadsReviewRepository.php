<?php

namespace App\Repository\LeadsReview;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ILeadsReviewRepository extends IRepository
{
	public function index(array $columns = ['*'], array $relations = [], array $payload): Collection;
	public function checked(string $leadId): bool;
	public function create_ticket(string $leadId, array $payload): bool;
	public function index_ticket(array $columns = ['*'], array $relations = []): Collection;
	public function page_ticket(int $page, array $payload, array $columns = ['*'], array $relations = []): Collection;
	public function get_ticket(string $modelId, array $columns = ['*'], array $relations = [], array $appends = []): ?Model;
	public function update_ticket(string $modelId, array $payload): bool;
}