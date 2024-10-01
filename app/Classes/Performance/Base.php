<?php

namespace App\Classes\Performance;

use MongoDB\BSON\UTCDateTime;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoDBObjects;

class Base
{
    private $performance_statuses_cache = null;

    public $error_types = [
        'business' => 'Business',
        'tech' => 'Tech',
        'internal' => 'Internal',
        'unknown' => 'Unknown',
    ];

    protected function parse_timeframe($timeframe)
    {
        $explode = explode(' - ', $timeframe);
        return [
            'start' => new UTCDateTime(strtotime($explode[0] . " 00:00:00") * 1000),
            'end' =>   new UTCDateTime(strtotime($explode[1] . " 23:59:59") * 1000),
        ];
    }

    protected function get_endpoint_names()
    {
        $where = [];
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $partners = $mongo->findMany();
        $result = [];
        foreach ($partners as $partner) {
            $result[MongoDBObjects::get_id($partner)] = $partner['token'] ?? '';
        }
        return $result;
    }

    protected function get_broker_names()
    {
        $where = ['partner_type' => '1'];
        $mongo = new MongoDBObjects('partner', $where);
        $partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1, 'partner_name' => 1]]);
        $result = [];
        foreach ($partners as $partner) {
            $result[MongoDBObjects::get_id($partner)] = GeneralHelper::broker_name($partner);
        }
        return $result;
    }

    protected function get_integration_names()
    {
        $where = [];
        $mongo = new MongoDBObjects('Integrations', $where);
        $integrations = $mongo->findMany();
        $result = [];
        foreach ($integrations as $integration) {
            $result[MongoDBObjects::get_id($integration)] = $integration['name'];
        }
        return $result;
    }

    protected function get_performance_statuses()
    {
        if ($this->performance_statuses_cache == null) {
            $where = [];
            $mongo = new MongoDBObjects('broker_performance_statuses', $where);
            $result = $mongo->findMany(['sort' => ['priority' => -1]]);
            $this->performance_statuses_cache = $result;
        }
        return $this->performance_statuses_cache;
    }

    protected function get_error_type($message)
    {
        $message = $this->get_error_name($message);
        $performance_statuses = $this->get_performance_statuses();
        foreach ($performance_statuses as $data) {
            $pattern = preg_replace('/\s+/i', '.*?', $data['status'] ?? '');
            if (preg_match('/' . $pattern . '/i', $message)) {
                return $data['category'];
            }
        }
        return 'unknown';
    }

    protected function get_error_name($message)
    {
        if (!is_string($message)) {
            $message = json_encode($message);
        }
        $json = json_decode($message, true);
        if ($json == null) {
            if (preg_match('#<body.*?</body>#is', $message, $m)) {
                $message = $m[0];
            }
            return strip_tags($message);
        }
        if (is_array($json['messages'] ?? null)) {
            return implode(' ', $json['messages']);
        }
        if (is_string($json['message'] ?? null)) {
            return $json['message'];
        }
        if (is_string($json['Message'] ?? null)) {
            return $json['Message'];
        }
        if (is_string($json['data'] ?? null)) {
            return $json['data'];
        }
        if (is_string($json['statusCode'] ?? null)) {
            return $json['statusCode'];
        }
        if (is_array($json['errors'] ?? null)) {
            return implode(' ', $json['errors']);
        }
        if (is_array($json['error'] ?? null)) {
            try {
                return implode(' ', $json['error']);
            } catch (\Exception $ex) {
                return print_r($json['error'], true);
            }
        }        //TODO Error Name Classifier
        return $message;
    }
}
