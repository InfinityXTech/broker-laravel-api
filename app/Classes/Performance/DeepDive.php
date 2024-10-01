<?php

namespace App\Classes\Performance;

use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\Stopwatch;

class DeepDive extends Base
{
    private $deep_dive_data;

    public function __construct($brokerId, $endpointId, $apivendorId, $timeframe, $country_code, $language_code, $error_type)
    {
        $this->deep_dive_data = $this->query_deep_dive($brokerId, $endpointId, $apivendorId, $timeframe, $country_code, $language_code, $error_type);
    }

    public function general(): array
    {
        $response = [];
        foreach ($this->deep_dive_data['invalid_by_country'] as $message_orig => $counts) {

            $count = array_sum(array_map(fn ($item) => $item['counts'], $counts));
            $total_traffic = $count + array_sum($this->deep_dive_data['valid']) + PHP_FLOAT_EPSILON;

            $message = str_replace(',', ', ', $message_orig);
            $message = str_replace('<', '&lt;', $message);

            if ($message == '') $message = 'Unknown';

            $error_type = array_pop($counts)['type'];

            $response[] = [
                'message' => $message,
                'type' => $error_type,
                'count' => $count,
                'cr' => 100 * $count / $total_traffic,
            ];
        }
        return $response;
    }

    public function country(): array
    {
        $response = [];
        foreach ($this->deep_dive_data['invalid_by_country'] as $message_orig => $counts) {
            foreach ($counts as $country => $data) {
                $total_traffic = $data['counts'] + ($this->deep_dive_data['valid'][$country] ?? 0) + PHP_FLOAT_EPSILON;
                $message = str_replace(',', ', ', $message_orig);
                $message = str_replace('<', '&lt;', $message);
				
				if ($message == '') $message = 'Unknown';

                $response[] = [
                    'message' => $message,
                    'type' => $data['type'],
                    'count' => $data['counts'],
                    'name' => $country,
                    'cr' => 100 * $data['counts'] / $total_traffic,
                ];
            }
        }
        return $response;
    }

    public function vendor(): array
    {
        $integration_names = $this->get_integration_names();

        $response = [];
        foreach ($this->deep_dive_data['invalid_by_vendor'] as $message_orig => $counts) {
            foreach ($counts as $vendor => $data) {
                $total_traffic = $data['counts'] + ($this->deep_dive_data['valid'][$vendor] ?? 0) + PHP_FLOAT_EPSILON;
                $message = str_replace(',', ', ', $message_orig);
                $message = str_replace('<', '&lt;', $message);
				
				if ($message == '') $message = 'Unknown';

                $response[] = [
                    'message' => $message,
                    'type' => $data['type'],
                    'count' => $data['counts'],
                    'name' => ($integration_names[$vendor] ?? $vendor),
                    'cr' => 100 * $data['counts'] / $total_traffic,
                ];
            }
        }
        return $response;
    }

    private function query_deep_dive($brokerId, $endpointId, $apivendorId, $timeframe, $country_code, $language_code, $error_type)
    {
        Stopwatch::start();

        $broker_integrations = null;
        if (!empty($apivendorId)) {
            $where = ['apivendor' => $apivendorId];
            $mongo = new MongoDBObjects('broker_integrations', $where);
            $broker_integrations = $mongo->findMany(['projection' => ['_id' => 1]]);
            $broker_integrations = array_map(fn ($item) => (string)$item['_id'], $broker_integrations);
        }

        $times = $this->parse_timeframe($timeframe);
        $where = [
            'test_lead' => 0,
            'match_with_broker' => 1,
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if (!empty($brokerId)) {
            $where['brokerId'] = $brokerId;
        }
        if (!empty($endpointId)) {
            $where['TrafficEndpoint'] = $endpointId;
        }
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
        $list = $mongo->aggregate([
            'group' => [
                '_id' => '$country',
                'count' => ['$sum' => 1]
            ]
        ], false, false);

        $valid_leads_count = [];
        foreach ($list as $group) {
            $country = $group['_id'];
            $valid_leads_count[$country] = $group['count'];
        }

        $where = [
            'requestResponse.success' => false,
            'requestResponse.integration.partnerId' => ['$exists' => true],
            'requestResponse.integration.apivendor' => ['$exists' => true],
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        if (!empty($brokerId)) {
            $where['requestResponse.integration.partnerId'] = $brokerId;
        }
        if (!empty($endpointId)) {
            $where['TrafficEndpoint'] = $endpointId;
        }
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
                    '_id' => [
                        'name' => '$requestResponse.response',
                        'country' => '$country',
                        'apivendor' => '$requestResponse.integration.apivendor',
                    ],
                    'count' => ['$addToSet' => '$user_id'],
                ]
            ],
            [
                '$set' => [
                    'count' => ['$size' => '$count']
                ]
            ],
        ]], false, false);

        $invalid_by_country = [];
        $invalid_by_vendor = [];
        foreach ($list as $group) {
            $message = $group['_id']['name'] ?? '';
            $type = $this->get_error_type($message);

            if ($error_type != null && $type != $error_type) {
                continue;
            }

            $name = $this->get_error_name($message);
            $country = $group['_id']['country'];
            $apivendor = $group['_id']['apivendor'];

            if (!isset($invalid_by_country[$name][$country])) {
                $invalid_by_country[$name][$country] = [
                    'type' => ($this->error_types[$type] ?? $type),
                    'counts' => 0,
                ];
            }
            $invalid_by_country[$name][$country]['counts'] += $group['count'];

            if (!isset($invalid_by_vendor[$name][$apivendor])) {
                $invalid_by_vendor[$name][$apivendor] = [
                    'type' => ($this->error_types[$type] ?? $type),
                    'counts' => 0,
                ];
            }
            $invalid_by_vendor[$name][$apivendor]['counts'] += $group['count'];
        }

        return [
            'time' => Stopwatch::stop(),
            'valid' => $valid_leads_count,
            'invalid_by_country' => $invalid_by_country,
            'invalid_by_vendor' => $invalid_by_vendor,
        ];
    }
}
