<?php

namespace App\Repository\TrafficEndpoints;

use App\Models\User;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use App\Classes\History\HistoryDiff;

use Illuminate\Support\Facades\Auth;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Models\TrafficEndpoints\TrafficEndpointPayout;
use App\Models\TrafficEndpoints\TrafficEndpointPayoutLog;
use App\Repository\TrafficEndpoints\ITrafficEndpointPayoutsRepository;

class TrafficEndpointPayoutsRepository extends BaseRepository implements ITrafficEndpointPayoutsRepository
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
    public function __construct(TrafficEndpointPayout $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], string $trafficEndpointId, array $relations = []): Collection
    {
        $items = $this->model->with($relations)->where(['TrafficEndpoint' => $trafficEndpointId])->get($columns)->toArray();
        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();
        foreach ($items as &$item) {
            $item['country'] = ['code' => $item['country_code'] ?? '', 'name' => $countries[strtolower($item['country_code'] ?? '')] ?? ''];
            $item['language'] = ['code' => $item['language_code'] ?? 'general', 'name' => $languages[$item['language_code'] ?? ''] ?? 'general'];
        }
        return new \Illuminate\Database\Eloquent\Collection($items);
    }

    public function create(array $payload): ?Model
    {
        $query = TrafficEndpointPayout::query()
            ->where('TrafficEndpoint', '=', $payload['TrafficEndpoint'])
            ->where('country_code', '=', $payload['country_code']);

        $query = $query->where(function ($q) use ($payload) {
            $q->orWhere('language_code', '=', ($payload['language_code'] ?? ''))->orWhere('language_code', '=', ($payload['language_code'] ?? null));
        });

        $count = $query->get()->count();

        if ($count > 0) {
            throw new \Exception('Payout with your country and language is already exist');
        }

        $model = $this->model->create($payload);
        return $model->fresh();
    }

    private function PayoutLog(string $action, string $modelId, array $payload)
    {
        $payout = TrafficEndpointPayout::query()->findOrFail($modelId)->get()->toArray();

        $foreign_key = 'TrafficEndpoint';
        if ($action == 'UPDATE_PAYOUT') {
            $insert['payout_pre'] = $payout['payout'] ?? 0;
            $insert['payout'] = $payload['payout'] ?? 0;
        }
        if ($action == 'UPDATE_COST_TYPE') {
            $insert['cost_type_pre'] = $payout['cost_type'] ?? 0;
            $insert['cost_type'] = $payload['cost_type'] ?? 0;
        }
        if ($action == 'DELETE') {
            $insert = $payout;
            unset($insert['_id']);
        }
        $insert['action'] = $action;
        $insert[$foreign_key] = $modelId;
        $insert['description'] = $payload['description'];
        $insert['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000);
        $insert['action_by'] = Auth::id();

        $mode = new TrafficEndpointPayoutLog();
        $mode->fill($insert);
        $mode->save();
    }

    public function update(string $modelId, array $payload): bool
    {
        if (!empty($payload['payout'])) {
            $this->PayoutLog('UPDATE_PAYOUT', $modelId, $payload);
        }
        if (!empty($payload['cost_type'])) {
            $this->PayoutLog('UPDATE_COST_TYPE', $modelId, $payload);
        }

        unset($payload['description']);

        $model = TrafficEndpointPayout::findOrFail($modelId);

        return $model->update($payload);
    }

    public function delete(string $modelId): bool
    {
        $this->PayoutLog('DELETE', $modelId, []);
        $model = TrafficEndpointPayout::findOrFail($modelId);
        return $model->delete($modelId);
    }

    public function log(string $payoutId, $limit = 20): array
    {
        $where = [
            'primary_key' => new \MongoDB\BSON\ObjectId($payoutId),
            'collection' => 'endpoint_payouts'
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
                    $diff->value('distributions_crg', 'Distributions CRG'),
                    $diff->value('weekend_off_distributions_crg', 'Off Weekend Distributions CRG'),
                    $diff->value('daily_cap', 'Daily Cap'),
                ]))
            ];
        }

        return $response;
    }
}
