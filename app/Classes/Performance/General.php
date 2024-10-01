<?php

namespace App\Classes\Performance;

use App\Classes\Mongo\MongoDBObjects;

class General extends Base
{
    public function endpoints(array $payload): array
    {
        $times = $this->parse_timeframe($payload['timeframe']);
        $where = [
            'test_lead' => 0,
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        $mongo = new MongoDBObjects('leads', $where);
        $list = $mongo->aggregate([
            'group' => [
                '_id' => [
                    'match_with_broker' => '$match_with_broker',
                    'endpointId' => '$TrafficEndpoint',
                ],
                'count' => ['$sum' => 1]
            ]
        ], false, false);

        $endpoint_names = $this->get_endpoint_names();

        $endpoints = [];
        foreach ($list as $group) {
            $match = $group['_id']['match_with_broker'] ? 'match' : 'reject';
            $id = $group['_id']['endpointId'];

            if (!isset($endpoints[$id])) {
                $endpoints[$id] = [ 
                    'name' => $endpoint_names[$id] ?? '', 
                    'match' => 0, 
                    'reject' => 0, 
                    'total' => 0
                ];
            }
            $endpoints[$id][$match]  += $group['count'];
            $endpoints[$id]['total'] += $group['count'];
        }

        $endpoints = array_filter($endpoints, function ($data) { return $data['reject'] > 0; });
        uasort($endpoints, function ($a, $b) { return strcmp($a['name'], $b['name']); });

        $response = [];
        foreach ($endpoints as $id => $data) {
            $response[] = [
                'endpoint' => $id,
                'name' => $data['name'],
                'total' => $data['total'],
                'match' => $data['match'],
                'reject' => $data['reject'],
                'cr' => 100 * $data['reject'] / $data['total'],
            ];
        }
        return $response;
    }

    public function brokers(array $payload): array
    {
        $times = $this->parse_timeframe($payload['timeframe']);
        $where = [
            'test_lead' => 0,
            'match_with_broker' => 1,
            'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
        ];
        $mongo = new MongoDBObjects('leads', $where);
        $list = $mongo->aggregate([
            'group' => [
                '_id' => '$brokerId',
                'count' => ['$sum' => 1]
            ]
        ], false, false);

        $broker_names = $this->get_broker_names();

        $brokers = [];
        foreach ($list as $group) {
            $id = $group['_id'];

            if (!isset($brokers[$id])) {
                $brokers[$id] = [ 
                    'name' => $broker_names[$id] ?? '', 
                    'match' => 0, 
                    'reject' => 0, 
                    'total' => 0
                ];
            }
            $brokers[$id]['match'] += $group['count'];
            $brokers[$id]['total'] += $group['count'];
        }

        $mongo = new MongoDBObjects('logs_serving', []);
        $list = $mongo->aggregate(['pipeline' => [
            [
                '$match' => [
                    'requestResponse.success' => false,
                    'requestResponse.integration.partnerId' => [ '$exists' => true ],
                    'Timestamp' => ['$gte' => $times['start'], '$lte' => $times['end']],
                ]                
            ],
            [
                '$group' => [
                    '_id' => '$requestResponse.integration.partnerId',
                    'count' => [ '$addToSet' => '$user_id' ],
                ]
            ],
            [
                '$set' => [
                    'count' => ['$size' => '$count']
                ]
            ],
        ]], false, false);

        foreach ($list as $group) {
            $id = $group['_id'];

            if (!isset($brokers[$id])) {
                $brokers[$id] = [ 
                    'name' => $broker_names[$id] ?? '', 
                    'match' => 0, 
                    'reject' => 0, 
                    'total' => 0
                ];
            }
            $brokers[$id]['reject'] += $group['count'];
            $brokers[$id]['total']  += $group['count'];
        }

        $brokers = array_filter($brokers, function ($data) { return $data['reject'] > 0; });
        uasort($brokers, function ($a, $b) { return strcmp($a['name'], $b['name']); });
        
        $response = [];
        foreach ($brokers as $id => $data) {
            $response[] = [
                'broker' => $id,
                'name' => $data['name'],
                'total' => $data['total'],
                'match' => $data['match'],
                'reject' => $data['reject'],
                'cr' => 100 * $data['reject'] / $data['total'],
            ];
        }
        return $response;
    }
}