<?php

namespace App\Repository\Affiliates;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IAffiliateRepository extends IRepository {
    public function index(array $columns = ['*'], array $relations = [], array $where = []): array;
    public function create(array $payload): ?Model;
    public function reset_password(string $affiliateId): array;
    public function un_payable(array $payload): array;
    public function stat_under_review(): array;
    public function application_approve(string $affiliateId): bool;
    public function application_reject(string $affiliateId): bool;
    public function draft(string $affiliateId): bool;
    public function delete(string $affiliateId): bool;
    public function sprav_offers(): array;
    public function register(array $payload): bool;
}