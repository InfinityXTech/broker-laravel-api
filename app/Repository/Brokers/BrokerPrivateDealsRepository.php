<?php

namespace App\Repository\Brokers;

use App\Models\User;
use MongoDB\BSON\ObjectId;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use App\Classes\History\HistoryDiff;

use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Models\Brokers\BrokerPrivateDeal;
use App\Models\TrafficEndpoint;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Brokers\IBrokerPrivateDealsRepository;
use App\Models\TrafficEndpoints\TrafficEndpointSubPublisherToken;

class BrokerPrivateDealsRepository extends BaseRepository implements IBrokerPrivateDealsRepository
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
    public function __construct(BrokerPrivateDeal $model)
    {
        $this->model = $model;
    }

    private function get_stat(string $brokerId): array
    {
        $where = [
            'brokerId' => $brokerId, //['$exists' => true, '$ne' => ''],
            'broker_crg_percentage_id' => ['$exists' => true, '$ne' => ''],
            'match_with_broker' => 1,
            'test_lead' => 0
        ];

        $group_by = [
            'leads' => ['$sum' => '$leads'],
            'ftds' => ['$sum' => '$depositors']
        ];
        $group_by['_id'] = [
            'broker_crg_percentage_id' => '$broker_crg_percentage_id',
        ];

        $agregate = [
            'pipeline' => [
                [
                    '$match' => $where,
                ],
                [
                    '$project' => ['_id' => 1, 'brokerId' => 1, 'broker_crg_percentage_id' => 1, 'depositor' => 1]
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
            $broker_crg_percentage_id = ((array)$item['_id'])['broker_crg_percentage_id'] ?? '';
            unset($item['_id']);

            $item['cr'] = round($item['leads'] > 0 ? 100 * $item['ftds'] / $item['leads'] : 0, 2) . '%';

            $result[$broker_crg_percentage_id] = $item;
        }

        return $result;
    }

    public function index(array $columns = ['*'], string $brokerId, array $relations = []): array
    {
        $items = $this->model->with($relations)->where(['broker' => $brokerId])->get($columns)->toArray();
        $stats = $this->get_stat($brokerId);

        $trafficEndpointIds = [];
        $subPublishers = [];

        foreach ($items as $item) {
            $trafficEndpointIds = array_unique(array_merge($trafficEndpointIds, (array)($item['endpoint'] ?? [])));
            $subPublishers = array_unique(array_merge($subPublishers, (array)($item['sub_publisher_list'] ?? [])));
        }

        $subPublisherTokens = [];
        if (!empty($trafficEndpointIds) && !empty($subPublishers)) {
            $_subPublisherTokens = TrafficEndpointSubPublisherToken::query()
                ->whereIn('traffic_endpoint', $trafficEndpointIds)
                ->whereIn('sub_publisher', $subPublishers)
                ->get(['traffic_endpoint', 'token', 'sub_publisher'])
                ->toArray();
            foreach ($_subPublisherTokens as $sp) {
                $subPublisherTokens[$sp['traffic_endpoint']][] = $sp;
            }
        }

        $endpoint_ids = [];
        foreach ($items as &$item) {
            foreach (['ignore_endpoints', 'endpoint'] as $f) {
                $r = (array)($item[$f] ?? []);
                $endpoint_ids = array_unique(array_merge($endpoint_ids, $r));
            }
        }

        $endpoint_ids = array_filter($endpoint_ids, fn ($value) => !is_null($value) && $value !== '');
        $endpoint_ids = array_map(fn ($f) => new \MongoDB\BSON\ObjectId($f), $endpoint_ids);
        $_endpoints = [];
        if (!empty($endpoint_ids)) {
            $_endpoints = TrafficEndpoint::query()->whereIn('_id', $endpoint_ids)->get(['_id', 'token'])->toArray();
        }
        $endpoints = [];
        foreach ($_endpoints as $endpoint) {
            $endpoints[(string)$endpoint['_id']] = $endpoint['token'];
        }

        foreach ($items as &$item) {
            $item['min_crg'] = (float)($item['min_crg'] ?? 0);
            if (isset($stats[(string)$item['_id']])) {
                $item['stat'] = $stats[(string)$item['_id']];
            } else {
                $item['stat'] = ['leads' => 0, 'ftds' => 0, 'cr' => 0];
            }

            $item['endpoint_str'] = '';
            $ignore_endpoints = (array)($item['ignore_endpoints'] ?? []);
            if (!empty($ignore_endpoints)) {
                $item['endpoint_str'] .= 'Ignore: ' . implode(', ', array_map(fn ($f) => $endpoints[$f] ?? '', $ignore_endpoints));
            }
            $only_endpoints = (array)($item['endpoint'] ?? []);
            if (!empty($only_endpoints)) {
                $item['endpoint_str'] .= 'Only: ' . implode(', ', array_map(fn ($f) => $endpoints[$f] ?? '', $only_endpoints));
            }

            if (is_string($item['country_code']) && !empty($item['country_code'])) {
                $item['country_code'] = [$item['country_code']];
            }
            if (is_string($item['language_code']) && !empty($item['language_code'])) {
                $item['language_code'] = [$item['language_code']];
            }

            $item['sub_publisher_tokens'] = [];
            if (!is_string($item['endpoint'] ?? [])) {
                foreach ($item['endpoint'] ?? [] as $endpointId) {
                    $item['sub_publisher_tokens'] = (array)array_merge($item['sub_publisher_tokens'], $subPublisherTokens[$endpointId] ?? []);
                }
            }
        }

        return $items;
    }

    private function check_similar_private_deal($brokerId, $data, $dealId = null)
    {
        // check if exists the same
        $where = [
            'broker' => $brokerId,
            'status' => ['$in' => ['1', 1]],
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

        $or = [];
        $endpoint = (array)($data['endpoint'] ?? []);
        if (!empty($endpoint)) {
            $where['endpoint'] = ['$in' => $endpoint];
        } else {
            $or[] = ['endpoint' => []];
            $or[] = ['endpoint' => null];
            $or[] = ['endpoint' => ['$size' => 0]];
            $or[] = ['endpoint' => ['$exists' => false]];
        }
        if (count($or) > 0) {
            $where['$and'][] = ['$or' => $or];
        }

        foreach (['country_code', 'language_code', 'only_integrations'] as $field_name) {
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

        // GeneralHelper::PrintR($where);die();

        $mongo = new MongoDBObjects('broker_crg', $where);
        $data = $mongo->findMany();
        if ($data && count($data) > 0) {
            return $data;
        }
        return false;
    }

    public function create(array $payload): ?Model
    {
        $data = $this->check_similar_private_deal($payload['broker'], $payload);
        if ((int)($payload['status'] ?? 0) == 1 && $data !== false) {
            throw new \Exception('There is already exists such a private deal');
        }

        $endpoints = (array)($payload['endpoint'] ?? []);
        $ignore_endpoints = (array)($payload['ignore_endpoints'] ?? []);

        if (count($endpoints) > 0 && count($ignore_endpoints) > 0) {
            if (count(array_filter($endpoints, fn (string $endpoint) => in_array($endpoint, $ignore_endpoints))) > 0) {
                throw new \Exception('You can\'t use the same Traffic Endpoint in fields "Deal For Endpoint" and "Ignore Endpoint"');
            }
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
            if ($this->check_similar_private_deal($model->broker, $payload, $modelId) !== false) {
                throw new \Exception('You can\'t turn it on because of there is a similar deal. Create a new one or contact your administrator');
            }
        }

        if (
            ((int)($data['status'] ?? 0) == 0) &&
            (((int)$payload['status'] ?? 0) == 1)
        ) {
            // if ($this->check_similar_private_deal($model->broker, $payload) !== false) {
            //     throw new \Exception('You can\'t turn it on because of there is a similar deal. Create a new one or contact your administrator');
            // }
            if ($this->has_leads($model->broker, $modelId)) {
                throw new \Exception('You can\'t turn it on because of this deal already has leads. Create a new one or contact your administrator');
            }
        }

        $endpoints = (array)($payload['endpoint'] ?? []);
        $ignore_endpoints = (array)($payload['ignore_endpoints'] ?? []);

        if (count($endpoints) > 0 && count($ignore_endpoints) > 0) {
            if (count(array_filter($endpoints, fn (string $endpoint) => in_array($endpoint, $ignore_endpoints))) > 0) {
                throw new \Exception('You can\'t use the same Traffic Endpoint in fields "Deal For Endpoint" and "Ignore Endpoint"');
            }
        }

        $payload['status'] = (int)$payload['status'];
        return $model->update($payload);
    }

    public function has_leads(string $brokerId, string $id): bool
    {
        $where = [
            'brokerId' => $brokerId,
            'match_with_broker' => 1,
            'test_lead' => 0,
            '$or' => [
                ['broker_crg_percentage_id' => $id],
                ['broker_changed_crg_percentage_id' => $id],
                ['broker_crg_payout_id' => $id],
                ['broker_cpl_deal_id' => $id],
            ]
        ];
        $mongo = new MongoDBObjects('leads', $where);
        return $mongo->count() > 0;
    }

    public function logs(string $modelId, $limit = 20): array
    {
        $where = [
            'primary_key' => new ObjectId($modelId),
            'collection' => 'broker_crg'
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

                    $diff->custom('type', 'Deal Type', fn ($item) => BrokerPrivateDeal::deal_types[$item] ?? ''),

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
