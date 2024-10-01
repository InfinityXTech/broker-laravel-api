<?php

namespace App\Repository\Campaigns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ICampaignTargetingLocationsRepository extends IRepository {
    public function index(array $columns = ['*'], string $campaignId, array $relations = []): Collection;
    public function create(array $payload): ?Model;
}