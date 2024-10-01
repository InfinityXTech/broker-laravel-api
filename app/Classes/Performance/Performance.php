<?php

namespace App\Classes\Performance;

use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\Stopwatch;

class Performance extends Base
{
    public function endpoints(string $timeframe, ?string $endpointId, ?string $country_code, ?string $language_code): array
    {
        Stopwatch::start();
        $times = $this->parse_timeframe($timeframe);
        $where = [
            'test_lead' => 0,
            'match_with_broker' => 1,
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if (!empty($endpointId)) {
            $where['TrafficEndpoint'] = $endpointId;
        }
        if (!empty($country_code)) {
            $where['country'] = strtoupper($country_code);
        }
        if (!empty($language_code)) {
            $where['language'] = strtolower($language_code);
        }
        $mongo = new MongoDBObjects('leads', $where);
        $valid_leads = $mongo->findMany(['projection' => ['_id' => 1]]);
        $valid_leads = array_map(fn($item) => (string)$item['_id'], $valid_leads);

        $where = [
            'user_id' => ['$nin' => $valid_leads],
            'requestResponse.success' => false,
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if (!empty($endpointId)) {
            $where['TrafficEndpoint'] = $endpointId;
        }
        if (!empty($country_code)) {
            $where['country'] = strtoupper($country_code);
        }
        if (!empty($language_code)) {
            $where['language'] = strtolower($language_code);
        }
        $mongo = new MongoDBObjects('logs_serving', []);
        $list = $mongo->aggregate(['pipeline' => [
            [
                '$match' => $where
            ],
            [
                '$group' => [
                    '_id' => '$requestResponse.response',
                    'count' => [ '$addToSet' => '$user_id' ],
                ]
            ],
            [
                '$set' => [
                    'count' => ['$size' => '$count']
                ]
            ],
        ]], false, false);

        $invalid_leads = array_fill_keys(array_keys($this->error_types), 0);
        $invalid_leads_count = 0;

        foreach ($list as $group) {
            $error_message = $group['_id'] ?? '';
            $error_type = $this->get_error_type($error_message);
            $invalid_leads[$error_type] += $group['count'];
            $invalid_leads_count += $group['count'];
        }

        $total_traffic = count($valid_leads) + $invalid_leads_count + PHP_FLOAT_EPSILON;

        Stopwatch::stop();

        $response = [];
        foreach ($this->error_types as $error_type => $error_name) {
            $error_count = $invalid_leads[$error_type] ?? 0;

            $response[] = [
                'type' => $error_type,
                'name' => $error_name,
                'count' => $error_count,
                'cr' => 100 * $error_count / $total_traffic,
            ];
        }
        $response[] = [
            'type' => '',
            'name' => 'All',
            'count' => $invalid_leads_count,
            'cr' => 100 * $invalid_leads_count / $total_traffic
        ];
        return $response;
    }

    public function brokers(string $timeframe, ?string $brokerId, ?string $country_code, ?string $language_code): array
    {
        $times = $this->parse_timeframe($timeframe);
        $where = [
            'test_lead' => 0,
            'match_with_broker' => 1,
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if (!empty($brokerId)) {
            $where['brokerId'] = $brokerId;
        }
        if (!empty($country_code)) {
            $where['country'] = strtoupper($country_code);
        }
        if (!empty($language_code)) {
            $where['language'] = strtolower($language_code);
        }
        $mongo = new MongoDBObjects('leads', $where);
        $valid_leads_count = $mongo->count();

        $where = [
            'requestResponse.success' => false,
            'requestResponse.integration.partnerId' => [ '$exists' => true ],
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if (!empty($brokerId)) {
            $where['requestResponse.integration.partnerId'] = $brokerId;
        }
        if (!empty($country_code)) {
            $where['country'] = strtoupper($country_code);
        }
        if (!empty($language_code)) {
            $where['language'] = strtolower($language_code);
        }
        $mongo = new MongoDBObjects('logs_serving', []);
        $list = $mongo->aggregate(['pipeline' => [
            [
                '$match' => $where
            ],
            [
                '$group' => [
                    '_id' => '$requestResponse.response',
                    'count' => [ '$addToSet' => '$user_id' ],
                ]
            ],
            [
                '$set' => [
                    'count' => ['$size' => '$count']
                ]
            ],
        ]], false, false);

        $invalid_leads = array_fill_keys(array_keys($this->error_types), 0);
        $invalid_leads_count = 0;

        foreach ($list as $group) {
            $error_message = $group['_id'] ?? '';
            $error_type = $this->get_error_type($error_message);
            $invalid_leads[$error_type] += $group['count'];
            $invalid_leads_count += $group['count'];
        }

        $total_traffic = $valid_leads_count + $invalid_leads_count + PHP_FLOAT_EPSILON;

        $response = [];        
        foreach ($this->error_types as $error_type => $error_name) {
            $error_count = $invalid_leads[$error_type] ?? 0;

            $response[] = [
                'type' => $error_type,
                'name' => $error_name,
                'count' => $error_count,
                'cr' => 100 * $error_count / $total_traffic
            ];
        }
        $response[] = [
            'type' => '',
            'name' => 'All',
            'count' => $invalid_leads_count,
            'cr' => 100 * $invalid_leads_count / $total_traffic
        ];
        return $response;
    }

    public function vendors(string $timeframe, ?string $apivendorId, ?string $country_code, ?string $language_code): array
    {
        $times = $this->parse_timeframe($timeframe);

        $broker_integrations = null;
        if (!empty($apivendorId)) {
            $where = [ 'apivendor' => $apivendorId ];
            $mongo = new MongoDBObjects('broker_integrations', $where);
            $broker_integrations = $mongo->findMany(['projection' => ['_id' => 1]]);
            $broker_integrations = array_map(fn($item) => (string)$item['_id'], $broker_integrations);
        }

        $where = [
            'test_lead' => 0,
            'match_with_broker' => 1,
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if ($broker_integrations != null) {
            $where['integrationId'] = ['$in' => $broker_integrations];
        }
        if (!empty($country_code)) {
            $where['country'] = strtoupper($country_code);
        }
        if (!empty($language_code)) {
            $where['language'] = strtolower($language_code);
        }
        $mongo = new MongoDBObjects('leads', $where);
        $valid_leads_count = $mongo->count();

        $where = [
            'requestResponse.success' => false,
            'requestResponse.integration.apivendor' => [ '$exists' => true ],
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if (!empty($apivendorId)) {
            $where['requestResponse.integration.apivendor'] = $apivendorId;
        }
        if (!empty($country_code)) {
            $where['country'] = strtoupper($country_code);
        }
        if (!empty($language_code)) {
            $where['language'] = strtolower($language_code);
        }
        $mongo = new MongoDBObjects('logs_serving', []);
        $list = $mongo->aggregate(['pipeline' => [
            [
                '$match' => $where
            ],
            [
                '$group' => [
                    '_id' => '$requestResponse.response',
                    'count' => [ '$addToSet' => '$user_id' ],
                ]
            ],
            [
                '$set' => [
                    'count' => ['$size' => '$count']
                ]
            ],
        ]], false, false);

        $invalid_leads = array_fill_keys(array_keys($this->error_types), 0);
        $invalid_leads_count = 0;

        foreach ($list as $group) {
            $error_message = $group['_id'] ?? '';
            $error_type = $this->get_error_type($error_message);
            $invalid_leads[$error_type] += $group['count'];
            $invalid_leads_count += $group['count'];
        }

        $total_traffic = $valid_leads_count + $invalid_leads_count + PHP_FLOAT_EPSILON;

        $response = [];        
        foreach ($this->error_types as $error_type => $error_name) {
            $error_count = $invalid_leads[$error_type] ?? 0;

            $response[] = [
                'type' => $error_type,
                'name' => $error_name,
                'count' => $error_count,
                'cr' => 100 * $error_count / $total_traffic
            ];
        }
        $response[] = [
            'type' => '',
            'name' => 'All',
            'count' => $invalid_leads_count,
            'cr' => 100 * $invalid_leads_count / $total_traffic
        ];
        return $response;
    }
}