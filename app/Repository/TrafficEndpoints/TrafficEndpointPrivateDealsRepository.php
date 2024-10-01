<?php

namespace App\Repository\TrafficEndpoints;

use App\Models\User;
use MongoDB\BSON\ObjectId;

use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use App\Classes\History\HistoryDiff;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Models\TrafficEndpoints\TrafficEndpointPrivateDeal;
use App\Repository\TrafficEndpoints\ITrafficEndpointPrivateDealsRepository;
use Exception;

class TrafficEndpointPrivateDealsRepository extends BaseRepository implements ITrafficEndpointPrivateDealsRepository
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
    public function __construct(TrafficEndpointPrivateDeal $model)
    {
        $this->model = $model;
    }

    private function get_stat(string $TrafficEndpoint): array
    {
        $where = [
            'TrafficEndpoint' => $TrafficEndpoint, //['$exists' => true, '$ne' => ''],
            'crg_percentage_id' => ['$exists' => true, '$ne' => ''],
            'match_with_broker' => 1,
            'test_lead' => 0
        ];

        $group_by = [
            'leads' => ['$sum' => '$leads'],
            'ftds' => ['$sum' => '$depositors']
        ];
        $group_by['_id'] = [
            'crg_percentage_id' => '$crg_percentage_id',
        ];

        $agregate = [
            'pipeline' => [
                [
                    '$match' => $where,
                ],
                [
                    '$project' => ['_id' => 1, 'TrafficEndpoint' => 1, 'crg_percentage_id' => 1, 'depositor' => 1]
                ],
                [
                    '$addFields' => [
                        'leads' => 1,
                        'depositors' => ['$toInt' => '$depositor']
                    ]
                ],
                [
                    '$group' => $group_by
                ],
            ]
        ];

        $mongo = new MongoDBObjects('leads', []);
        $find = $mongo->aggregate($agregate, false, false) ?? [];

        $result = [];
        foreach ($find as &$item) {
            $crg_percentage_id = ((array)$item['_id'])['crg_percentage_id'] ?? '';
            unset($item['_id']);

            $item['cr'] = round($item['leads'] > 0 ? 100 * $item['ftds'] / $item['leads'] : 0, 2) . '%';

            $result[$crg_percentage_id] = $item;
        }

        return $result;
    }

    public function index(array $columns = ['*'], string $brokerId, array $relations = []): array
    {
        $items = $this->model->with($relations)->where(['TrafficEndpoint' => $brokerId])->get($columns)->toArray();
        $countries = GeneralHelper::countries();
        $languages = GeneralHelper::languages();
        $stats = $this->get_stat($brokerId);
        foreach ($items as &$item) {
            if (is_string($item['country_code'])) {
                $item['country'] = ['code' => $item['country_code'] ?? '', 'name' => $countries[$item['country_code'] ?? ''] ?? ''];
            } else {
                $item['countries'] ??= [];
                foreach ($item['country_code'] ?? [] as $country) {
                    $item['countries'][] = ['code' => $country ?? '', 'name' => $countries[$country ?? ''] ?? ''];
                }
            }

            $language_codes = [];
            foreach ((array)($item['language_code'] ?? []) as $language) {
                $language_codes[] = ['code' => $language, 'name' => $languages[$language] ?? ''];
            }
            $item['languages'] = $language_codes;
            $item['min_crg'] = (float)($item['min_crg'] ?? 0);

            if (isset($stats[(string)$item['_id']])) {
                $item['stat'] = $stats[(string)$item['_id']];
            } else {
                $item['stat'] = ['leads' => 0, 'ftds' => 0, 'cr' => 0];
            }
        }
        return $items;
    }

    private function check_similar_private_deal($traffic_endpoint_id, $data, $dealId = null)
    {
        // check if exists the same
        $where = [
            'TrafficEndpoint' => $traffic_endpoint_id,
            'status' => ['1', 1],
            // 'country_code' => $data['country_code'],
        ];

        $type = strval($data['type']);
        switch ($type) {
            case '1':
            case '2': {
                    $where['type'] = ['$in' => [$type, '3']];
                    break;
                }
            case '3': {
                    $where['type'] = ['$in' => ['1', '2', '3']];
                    break;
                }
        }

        if (isset($dealId)) {
            $where['_id'] = ['$ne' => new ObjectId($dealId)];
        }

        $where['$and'] = [];

        foreach (['country_code', 'language_code'] as $field_name) {
            if (count($data[$field_name] ?? []) > 0) {
                $or = array_reduce((array)$data[$field_name], function (?array $carry, string $value) use ($field_name) {
                    $carry ??= [];
                    $carry[] = [$field_name => $value];
                    return $carry;
                }) ?? [];
                if (count($or) > 0) {
                    $where['$and'][] = ['$or' => $or];
                }
            }
        }

        foreach (['funnel_list', 'sub_publisher_list'] as $field_name) {
            if (count($data[$field_name] ?? []) > 0) {
                $or = array_reduce((array)$data[$field_name], function (?array $carry, string $value) use ($field_name) {
                    $carry ??= [];
                    $carry[] = [$field_name => $value];
                    return $carry;
                }) ?? [];
                if (count($or) > 0) {
                    $where['$and'][] = ['$or' => $or];
                }
            } else {
                $where['$and'][] = ['$or' => [[$field_name => []], [$field_name => null], [$field_name => ['$exists' => false]]]];
            }
        }

        if (empty($where['$and'] ?? [])) {
            unset($where['$and']);
        }

        $mongo = new MongoDBObjects('endpoint_crg', $where);
        $data = $mongo->findMany();
        if ($data && count($data) > 0) {
            return $data;
        }
        return false;
    }

    public function create(array $payload): ?Model
    {
        $data = $this->check_similar_private_deal($payload['TrafficEndpoint'], $payload);
        if ((int)($payload['status'] ?? 0) == 1 && $data !== false) {
            throw new Exception('There is already exists such a private deal');
        }

        $payload['status'] = (int)($payload['status'] ?? 0);

        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function update(string $modelId, array $payload): bool
    {
        $model = $this->findById($modelId);

        $data = $model->toArray();

        if (
            // ((int)($data['status'] ?? 0) == 1) ||
            ((int)($payload['status'] ?? 0) == 1)
        ) {
            if ($this->check_similar_private_deal($model->TrafficEndpoint, $payload, $modelId) !== false) {
                throw new \Exception('You can\'t turn it on because of there is a similar deal. Create a new one or contact your administrator');
            }
        }

        if (
            ((int)($data['status'] ?? 0) == 0) &&
            ((int)($payload['status'] ?? 0) == 1)
        ) {
            if ($this->has_leads($model->TrafficEndpoint, $modelId)) {
                throw new \Exception('You can\'t turn it on because of this deal already has leads. Create a new one or contact your administrator');
            }
        }

        $payload['status'] = (int)$payload['status'];

        return $model->update($payload);
    }

    public function has_leads(string $traffic_endpoint_id, string $id): bool
    {
        $where = [
            'TrafficEndpoint' => $traffic_endpoint_id, //['$exists' => true, '$ne' => ''],
            'match_with_broker' => 1,
            'test_lead' => 0,
            '$or' => [
                ['crg_percentage_id' => $id],
                ['changed_crg_percentage_id' => $id],
                ['crg_payout_id' => $id],
                ['cpl_deal_id' => $id],
            ]
        ];
        $mongo = new MongoDBObjects('leads', $where);
        return $mongo->count() > 0;
        // if ($leads != null && count($leads)) {
        //     return true;
        // }
        // return false;
    }

    public function logs(string $modelId, $limit = 20): array
    {
        $where = [
            'primary_key' => new ObjectId($modelId),
            'collection' => 'endpoint_crg'
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
                    $diff->value('status', 'Status'),

                    $diff->value('name', 'Deal Name'),
                    $diff->value('description', 'Description'),

                    $diff->endpoints('endpoint', 'Deal for Endpoints'),
                    $diff->endpoints('ignore_endpoints', 'Ignore Endpoints'),
                    $diff->integrations('only_integrations', 'Only Integrations'),

                    $diff->value('country_code', 'Countries'),
                    $diff->array('language_code', 'Languages'),

                    $diff->value('min_crg', 'Min CRG'),
                    $diff->value('max_crg_invalid', 'Max % invalid'),
                    $diff->value('calc_period_crg', 'CRG calculation period'),

                    $diff->value('limited_leads', 'Limited Leads'),
                    $diff->value('leads', 'Limited Leads Count'),

                    $diff->value('payout', 'Payout'),
                    $diff->array('ignore_lead_statuses', 'Ignore Leads Statuses'),

                    $diff->value('apply_crg_per_endpoint', 'Apply CRG per Endpoint'),

                    $diff->value('end_date', 'End Date'),

                    $diff->value('sub_publisher_list', 'Sub Publisher'),

                    $diff->value('funnel_list', 'Funnel'),

                    $diff->blocked_schedule('blocked_schedule', 'Schedule'),
                ]))
            ];
        }
        return $response;
    }
}
