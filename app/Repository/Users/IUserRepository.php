<?php

namespace App\Repository\Users;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IUserRepository extends IRepository
{
    public function index(array $columns = ['*'], array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function reset_password(string $userId, string $password): string;
    public function update_permissions(string $userId, array $payload): bool;
}
