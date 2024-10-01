<?php

namespace App\Repository\Brokers;

use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;

use Illuminate\Database\Eloquent\Model;
use App\Models\Brokers\BrokerIntegration;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Brokers\IBrokerIntegrationRepository;

class BrokerIntegrationRepository extends BaseRepository implements IBrokerIntegrationRepository
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
    public function __construct(BrokerIntegration $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): array
    {
        if (!in_array('partnerId', $columns)) {
            $columns[] = 'partnerId';
        }

        if (!in_array('status', $columns)) {
            $columns[] = 'status';
        }

        $items = BrokerIntegration::where($relations)
            ->with('integration')
            ->get($columns)
            ->sortByDesc(function ($item, $key) {
                return intval($item->status ?? 0);
            })
            ->values()
            ->toArray();

        foreach ($items as &$item) {

            $last_call = (array)($item['last_call'] ?? []);
            if (isset($last_call['milliseconds'])) {
                $last_call = (float)((time() - ($last_call['milliseconds'] / 1000)) / 60);
            } else {
                $last_call = null;
            }

            $general_cr = 0;
            $total_ftd = (int)(isset($item['total_ftd']) ? $item['total_ftd'] : 0);
            $total_leads = (int)(isset($item['total_leads']) ? $item['total_leads'] : 1);
            if ($total_ftd > 0) {
                $general_cr = (float)($total_ftd / $total_leads) * 100;
            }

            $item['last_api_call'] = $last_call;
            $item['cr'] = $general_cr;

            if (isset($item['name'])) {
                $item['name'] = GeneralHelper::broker_integration_name($item);
            }
        }
        return $items;
    }

    public function create(array $payload): ?Model
    {
        $payload['status'] = '2';
        $payload['last_call'] = 0;
        $payload['syncJob'] = true;

        $model = $this->model->create($payload);
        return $model->fresh();
    }
}
