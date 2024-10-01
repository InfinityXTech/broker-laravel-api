<?php

namespace App\Repository\Advertisers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IAdvertiserPostEventsRepository extends IRepository {
    public function index(string $advertiserId, array $columns = ['*'], array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function delete(string $advertiserId): bool;
}