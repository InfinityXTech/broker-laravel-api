<?php

namespace App\Repository\Advertisers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IAdvertisersRepository extends IRepository {
    public function index(array $columns = ['*'], array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function un_payable(array $payload): array;
    public function draft(string $advertiserId): bool;
    public function delete(string $advertiserId): bool;
}