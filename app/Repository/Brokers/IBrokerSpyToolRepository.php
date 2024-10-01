<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerSpyToolRepository extends IRepository {
    public function get_brokers_and_integrations(string $leadId): array;
    public function run(array $payload): array;
}