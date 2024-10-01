<?php

namespace App\Repository\Campaigns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ICampaignPrivateDealsRepository extends IRepository {
    public function index(array $columns = ['*'], string $campaignId, array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function log(string $payoutId, $limit = 20): array;
}