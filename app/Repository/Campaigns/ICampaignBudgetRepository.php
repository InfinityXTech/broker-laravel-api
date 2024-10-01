<?php

namespace App\Repository\Campaigns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ICampaignBudgetRepository extends IRepository {
    public function endpoint_allocations(array $columns = ['*'], string $campaignId, array $relations = []): Collection;
    public function endpoint_allocation_create(array $payload): ?Model;
    public function endpoint_allocation_update(string $allocationId, array $payload): ?Model;
    public function endpoint_allocation_delete(string $allocationId): bool;
}