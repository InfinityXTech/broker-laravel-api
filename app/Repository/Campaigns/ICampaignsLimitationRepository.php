<?php

namespace App\Repository\Campaigns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ICampaignsLimitationRepository extends IRepository {
    public function sub_publishers(array $columns = ['*'], string $campaignId, array $relations = []): Collection;
    public function sub_publishers_create(array $payload): ?Model;
    public function sub_publishers_update(string $subPublisherId, array $payload): bool;
    public function sub_publishers_delete(string $subPublisherId): bool;
}