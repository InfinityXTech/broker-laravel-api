<?php

namespace App\Repository\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ISupportRepository extends IRepository
{
    public function index(array $columns = ['*'], array $relations = []): Collection;
    public function page(int $page, array $payload, array $columns = ['*'], array $relations = []): Collection;
    public function create(array $payload): ?Model;
    public function get(
        string $modelId,
        array $columns = ['*'],
        array $relations = [],
        array $appends = []
    ): ?Model;
    public function update(string $modelId, array $payload): bool;
    public function send_comment(string $ticket_id, array $payload): bool;
}
