<?php

namespace App\Repository\Brokers;

use App\Classes\Brokers\ManageCaps;
use App\Models\Brokers\BrokerCaps;
use Illuminate\Database\Eloquent\Model;

use App\Repository\BaseRepository;
use App\Repository\Brokers\IBrokerCapsRepository;

class BrokerCapsRepository extends BaseRepository implements IBrokerCapsRepository
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(BrokerCaps $model)
    {
        $this->model = $model;
    }

    public function index(?array $filter = null): array
    {
        $caps = new ManageCaps();
        return $caps->feed_caps($filter);
    }

    public function cap_countries(string $brokerId): string
    {
        $caps = new ManageCaps();
        return $caps->download_cap_countries($brokerId);
    }

    public function logs(string $capId): array
    {
        $caps = new ManageCaps();
        return $caps->logs($capId);
    }

    public function available_endpoints(string $capId): array {
        $caps = new ManageCaps();
        return $caps->available_endpoints($capId);
    }

    public function create(array $payload): ?Model
    {
        $caps = new ManageCaps();
        return $caps->create($payload);
    }

    public function update(string $modelId, array $payload): bool
    {
        $caps = new ManageCaps();
        return $caps->update($modelId, $payload);
    }

    public function force_update(string $modelId, array $payload): bool
    {
        return parent::update($modelId, $payload);
    }
}
