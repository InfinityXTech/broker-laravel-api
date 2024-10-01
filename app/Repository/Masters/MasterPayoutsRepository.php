<?php

namespace App\Repository\Masters;

use Exception;
use App\Helpers\GeneralHelper;

use App\Repository\BaseRepository;
use App\Models\Masters\MasterPayout;
use Illuminate\Support\Facades\Auth;
use App\Models\Masters\MasterPayoutLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Masters\IMasterPayoutsRepository;

class MasterPayoutsRepository extends BaseRepository implements IMasterPayoutsRepository
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
    public function __construct(MasterPayout $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], string $masterId, array $relations = []): Collection
    {
        $items = $this->model->with($relations)->where(['master_partner' => $masterId])->get($columns);
        // $items = MasterPayout::where($relations)->get($columns);
        // return $items;

        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();
        foreach ($items as &$item) {
            $item['country'] = ['code' => $item['country_code'] ?? '', 'name' => $countries[$item['country_code'] ?? ''] ?? ''];
            $item['language'] = ['code' => $item['language_code'] ?? '', 'name' => $languages[$item['language_code'] ?? ''] ?? 'general'];
        }
        return new \Illuminate\Database\Eloquent\Collection($items);
    }

    public function create(array $payload): ?Model
    {
        $query = MasterPayout::query()
            ->where('master_partner', '=', $payload['master_partner'])
            ->where('country_code', '=', $payload['country_code']);

        $query = $query->where(function ($q)  use ($payload) {
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
        $payout = MasterPayout::query()->findOrFail($modelId)->get()->toArray();

        $foreign_key = 'master';
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

        $mode = new MasterPayoutLog();
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

        $model = MasterPayout::findOrFail($modelId);

        return $model->update($payload);
    }

    public function delete(string $modelId): bool
    {
        $this->PayoutLog('DELETE', $modelId, []);
        $model = MasterPayout::findOrFail($modelId);
        return $model->delete($modelId);
    }
}
