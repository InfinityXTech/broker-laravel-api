<?php

namespace App\Repository\Campaigns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ICampaignsRepository extends IRepository {
    public function index(string $advertiserId, array $columns = ['*'], array $relations = []): array;
    public function create(array $payload): ?Model;
    public function update(string $campaignId, array $payload): bool;
    public function draft(string $campoaignId): bool;
}