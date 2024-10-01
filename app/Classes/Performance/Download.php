<?php

namespace App\Classes\Performance;

use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\CryptHelper;
use App\Helpers\Stopwatch;

class Download extends Base
{
    public function run($brokerId, $endpointId, $apivendorId, $timeframe, $country_code, $language_code, $error_type, $error_message)
    {
        Stopwatch::start();

        $times = $this->parse_timeframe($timeframe);
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
        $mongo = new MongoDBObjects('logs_serving', $where);
        $list = $mongo->findMany([
            'projection' => [
                'requestResponse.response' => 1,
                'user_id' => 1,
                'country' => 1,
                'phone' => 1,
                'email' => 1,
                'Timestamp' => 1
            ]
        ]);

        $header = [
            'Timestamp',
            'Lead ID',
            'Country',
            'Email',
            'Phone',
            'Error Type',
            'Error Message',
        ];

        $response = [];
        $response[] = '"' . implode('","', $header) . '"';

        foreach ($list as $item) {
            $error_response = $item['requestResponse']['response'] ?? '';
            $type = $this->get_error_type($error_response);

            if ($error_type != null && $type != $error_type) {
                continue;
            }

            $message = $this->get_error_name($error_response);
            if ($error_message != null && $message != $error_message) {
                continue;
            }

            // --- Decrypt --- //
            $encrypted_fields = ['email', 'phone'];
            foreach ($encrypted_fields as $field) {
                if (strpos($item[$field] ?? '', '==') !== false) {
                    $item[$field] = CryptHelper::decrypt($item[$field] ?? '');
                }
            }

            $ts = ((array)$item['Timestamp'])['milliseconds'] / 1000;
            $row = [
                date('Y-m-d H:i:s', $ts),
                $item['user_id'],
                $item['country'],
                $item['email'],
                $item['phone'],
                $this->error_types[$type],
                trim(json_encode($message), '"'),
            ];
            $response[] = '"' . implode('","', $row) . '"';
        }

        Stopwatch::stop();
        return implode("\n", $response);
    }
}
