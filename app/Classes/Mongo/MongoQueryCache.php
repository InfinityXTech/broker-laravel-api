<?php

namespace App\Classes\Mongo;

use App\Classes\Mongo\MongoDBObjects;

class MongoQueryCache extends MongoQuery
{

    private $cache_old_data_days = 60;

    public function __construct($time_range, $collection, $parameterArray, $conditions, $orderby = null)
    {
        parent::__construct($time_range, $collection, $parameterArray, $conditions, $orderby);
    }

    private function prefix()
    {
        global $config;
        return '/mongo_query_' . $config['cache']['prefix'];
    }

    private function getQueryHash(&$pivot, &$schema)
    {
        return md5(
            $this->collection .
                serialize($pivot) .
                serialize($schema) .
                serialize($this->conditions) .
                serialize($this->parameterArray) .
                serialize($this->orderby)
        );
    }

    private function isOldPeriod()
    {
        return strtotime('-' . $this->cache_old_data_days . ' days') > ((array)$this->time_range['start'])['milliseconds'] / 1000;
    }

    private function checkOldDataCache($hash)
    {
        if (!isset($this->time_range['start']) || !isset($this->time_range['end'])) {
            return false;
        }

        $result = false;
        $start = ((array)$this->time_range['start'])['milliseconds'] / 1000;
        $end = ((array)$this->time_range['end'])['milliseconds'] / 1000;

        $cache_dir = dirname(dirname(__DIR__)) . '/cache';
        $files = glob($cache_dir . $this->prefix() . $hash . '_' . date('Y-m-d', $start) . '_*.cache');

        $file_cache = '';
        $file_max_end = 0;

        foreach ($files as $file) {
            $matches = [];
            if (preg_match($this->prefix() . '(.*?)_(.*?)_(.*?).cache/i', $file, $matches)) {

                $file_end = strtotime($matches[3] . ' 23:59:59');

                if (
                    $file_end <= $end &&
                    $file_end <= strtotime('-' . $this->cache_old_data_days . ' days 23:59:59')
                ) {
                    if ($file_max_end < $file_end) {
                        $file_max_end = $file_end;
                        $file_cache = $file;
                    }
                }
            }
        }

        if (!empty($file_cache) && file_exists($file_cache)) {
            $result = unserialize(file_get_contents($file_cache));

            touch($file_cache, time());

            $new_start = strtotime('+1 days 00:00:00', strtotime($result['end']));
            $this->time_range['start'] = new \MongoDB\BSON\UTCDateTime($new_start * 1000);
        }
        return $result;
    }

    private function getOldDataCache($hash)
    {
        if (!$this->isOldPeriod()) {
            return false;
        }

        $cache_data = $this->checkOldDataCache($hash);
        if ($cache_data) {
            return $cache_data;
        }

        $start = ((array)$this->time_range['start'])['milliseconds'] / 1000;
        $end = ((array)$this->time_range['end'])['milliseconds'] / 1000;
        $mid = strtotime('-' . $this->cache_old_data_days . ' days 23:59:59');

        if ($start < $mid && $mid < $end) {

            $this->time_range['end'] = new \MongoDB\BSON\UTCDateTime($mid * 1000);
            
            $cache_data = $this->queryMongoDataCache();

            $this->time_range['start'] = new \MongoDB\BSON\UTCDateTime($mid * 1000);
            $this->time_range['end'] = new \MongoDB\BSON\UTCDateTime($end * 1000);
        }
        return $cache_data;
    }

    private function saveOldDataCache($hash, &$data, &$raw_data)
    {
        $start = date('Y-m-d', ((array)$this->time_range['start'])['milliseconds'] / 1000);
        $end = date('Y-m-d', ((array)$this->time_range['end'])['milliseconds'] / 1000);

        $file_cache = dirname(dirname(__DIR__)) . '/cache' . $this->prefix() . $hash . '_' . $start . '_' . $end . '.cache';
        if (!file_exists($file_cache)) {
            $cache_data = [
                'start' => $start,
                'end' => $end,
                'data' => $data,
            ];
            file_put_contents($file_cache, serialize($cache_data));
        }
    }

    private function getKey(&$schema, &$row)
    {
        $key = '';
        foreach ($schema as $g) {
            $key .= $row[$g] . "\n";
        }
        return $key;
    }

    private function mergeAndgroupBy(&$schema, &$data1, &$data2)
    {
        $results = [];
        foreach ($data1 as $row) {
            $key = $this->getKey($schema, $row);
            $results[$key] = $row;
        }
        foreach ($data2 as $row) {
            $key = $this->getKey($schema, $row);

            if (!isset($results[$key])) {
                $results[$key] = $row;
            }
            else {
                $result = &$results[$key];

                foreach ($row as $key => $value) {
                    if (!in_array($key, $schema)) {
                        $result[$key] += $value;
                    }
                }
            }
        }
        return array_values($results);
    }

    private function queryMongoDataCache()
    {
        $pivot = $this->createpivot();
        $schema = $this->createSchema();
        $hash = $this->getQueryHash($pivot, $schema);

        $cache_data = $this->getOldDataCache($hash);

        $where = $this->routeConditions();

        $projection = $this->getProjectionFields($pivot, $schema);

        $args_query = [];

        if (count($projection) > 0) {
            $args_query['projection'] = $projection;
        }

        $mongo = new MongoDBObjects($this->collection, $where);
        $data = $mongo->findMany($args_query);

        // Filter cached raw data
        if ($cache_data !== false) {
            foreach ($data as $key => $datas) {
                if ($datas['Timestamp'] < $this->time_range['start']) {
                    unset($data[$key]);
                }
            }
        }

        $result = $this->buildData($data, $pivot, $schema);
        $break = $this->breaktoschema($result, $schema);

        if ($cache_data !== false) {
            $break = $this->mergeAndgroupBy($schema, $cache_data['data'], $break);
        } else {
            $this->saveOldDataCache($hash, $break, $data);
        }

        $cache_data = [
            'start' => date('Y-m-d', ((array)$this->time_range['start'])['milliseconds'] / 1000),
            'end' => date('Y-m-d', ((array)$this->time_range['end'])['milliseconds'] / 1000),
            'data' => $break,
        ];
        return $cache_data;
    }

    public function queryMongo($args = [])
    {
        global $config;
        if ($config['cache']['query_cache'] == false) {
            return parent::queryMongo($args);
        }
        if (count($args) == 0) {
            $cache_data = $this->queryMongoDataCache();
            return $cache_data['data'];
        }
        error_log("NO_QUERY_CACHE ".json_encode($args));
        return parent::queryMongo($args);
    }
}
