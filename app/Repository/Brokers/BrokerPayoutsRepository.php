<?php

namespace App\Repository\Brokers;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use App\Classes\History\HistoryDiff;

use App\Models\Brokers\BrokerPayout;
use App\Classes\Mongo\MongoDBObjects;
use App\Models\Brokers\BrokerPayoutLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Brokers\IBrokerPayoutsRepository;

class BrokerPayoutsRepository extends BaseRepository implements IBrokerPayoutsRepository
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
    public function __construct(BrokerPayout $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], string $brokerId, array $relations = []): Collection
    {
        return $this->model->with($relations)
            ->where(['broker' => $brokerId])
            ->get($columns)
            ->map(function ($model) {
                if (empty($model->language_code ?? '')) {
                    $model->language_code = 'general';
                }
                return $model;
            });
        // $items = BrokerPayout::where($relations)->get($columns);
        // return $items;
    }

    public function create(array $payload): ?Model
    {
        // $exists = $this->model->where([
        //     'broker' => $payload['broker'],
        //     'country_code' => $payload['country_code'],
        //     'language_code' => ($payload['language_code'] ?? ''),
        // ])->first();

        $query = BrokerPayout::query()
            ->where('broker', '=', $payload['broker'])
            ->where('country_code', '=', $payload['country_code']);

        $query = $query->where(function ($q)  use ($payload) {
            $q->orWhere('language_code', '=', ($payload['language_code'] ?? ''))->orWhere('language_code', '=', ($payload['language_code'] ?? null));
        });
        // GeneralHelper::PrintR([$query->toSql()]);die();
        $count = $query->get()->count();

        if ($count > 0) {
            throw new \Exception('There is already exists such a country and language');
        }

        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function update(string $modelId, array $payload): bool
    {
        $model = $this->findById($modelId);

        $diff = [];
        foreach ($payload as $key => $value) {
            if ($key != 'description' && $model->$key != $value) {
                $diff[$key . '_pre'] = $model->$key;
                $diff[$key] = $value;
            }
        }
        if (!empty($diff)) {
            $diff['action'] = 'UPDATE';
            $diff['broker'] = $model->broker;
            $diff['description'] = $payload['description'] ?? null;
            $diff['action_by'] = GeneralHelper::get_current_user_token();
            $diff['timestamp'] = GeneralHelper::ToMongoDateTime(time());
            BrokerPayoutLog::create($diff);
        }

        return $model->update($payload);
    }

    public function log(string $payoutId, $limit = 20): array
    {
        $where = [
            'primary_key' => new \MongoDB\BSON\ObjectId($payoutId),
            'collection' => 'broker_payouts'
        ];
        $mongo = new MongoDBObjects('history', $where);
        $history_logs = $mongo->findMany([
            'limit' => $limit,
            'sort' => ['timestamp' => -1]
        ]);

        $diff = new HistoryDiff;
        $response = [];

        for ($i = 0; $i < count($history_logs); $i++) {
            $history = $history_logs[$i];
            $diff->init($history, $history_logs[$i + 1] ?? null);

            $response[] = [
                'timestamp' => $history['timestamp'],
                'action' => $history['action'],
                'changed_by' => User::query()->find((string)$history['action_by'])->name,
                'data' => implode(', ', array_filter([
                    $diff->value('country_code', 'Country'),
                    $diff->value('language_code', 'Language'),
                    $diff->value('cost_type', 'Cost Type'),
                    $diff->value('payout', 'Payout'),
                    $diff->value('enabled', 'Enabled'),
                ]))
            ];
        }

        return $response;
    }
}
