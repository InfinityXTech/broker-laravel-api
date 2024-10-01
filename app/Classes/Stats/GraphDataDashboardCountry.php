<?php

namespace App\Classes\Stats;

use App\Helpers\QueryHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\Mongo\MongoDBObjects;

class GraphDataDashboardCountry
{

    public $filter;
    public $collection;

    public function __construct($filter, $collection)
    {
        $this->collection = $collection;
        $this->filter = $filter;
    }

    private function buildTimestamp($d)
    {
        $time_range = array();
        $time_range['start'] = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d", strtotime($d)) . " 00:00:00") * 1000);
        $time_range['end'] = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d", strtotime($d)) . " 23:59:59") * 1000);
        return $time_range;
    }

    public function queryForCountries()
    {

        $array = array();
        $array['Leads'] = array('Leads' => 'count');
        $array['country'] = array('country' => 'val');

        $conditions = QueryHelper::buildConditions($this->filter);

        $time = $this->buildTimestamp('today');
        $queryMongo = new MongoQuery($time, 'leads', $array, $conditions);
        $dataToday = $queryMongo->queryMongo();

        $time = $this->buildTimestamp('yesterday');
        $queryMongo = new MongoQuery($time, 'leads', $array, $conditions);
        $dataYesterday = $queryMongo->queryMongo();

        $db = array();

        foreach ($dataToday as $today_array) {
            if (isset($db[strtolower($today_array['country'])]['today'])) {
                $db[strtolower($today_array['country'])]['today'] = $db[strtolower($today_array['country'])]['today'] + $today_array['Leads'];
            } else {
                $db[strtolower($today_array['country'])]['today'] = $today_array['Leads'];
            }
        }

        foreach ($dataYesterday as $yesterday_array) {
            if (isset($db[strtolower($yesterday_array['country'])]['yesterday'])) {
                $db[strtolower($yesterday_array['country'])]['yesterday'] = $db[strtolower($yesterday_array['country'])]['yesterday'] + $yesterday_array['Leads'];
            } else {
                $db[strtolower($yesterday_array['country'])]['yesterday'] = $yesterday_array['Leads'];
            }
        }

        foreach ($db as $geo => $stats) {
            if (!isset($db[$geo]['today'])) {
                $db[$geo]['today'] = 0;
            }

            if (!isset($db[$geo]['yesterday'])) {
                $db[$geo]['yesterday'] = 0;
            }
        }

        $mongo = new MongoDBObjects($this->collection, $this->filter);

        $update = array();
        $update['GraphCountryDashboard'] = json_encode($db);

        $mongo->update($update);
    }
}
