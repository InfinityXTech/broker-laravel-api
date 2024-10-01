<?php

namespace App\Classes\Stats;

use App\Helpers\QueryHelper;
use App\Classes\Mongo\MongoQuery;
use App\Classes\Mongo\MongoDBObjects;

class GraphDataDashboardHourly
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

    public function todayTomorrowComparisonAccountLevel()
    {
        $array = array();
        $array['Leads'] = array('Leads' => 'count');
        $array[] = array('hour' => 'val');

        $conditions = QueryHelper::buildConditions($this->filter);

        $time = $this->buildTimestamp('today');
        $queryMongo = new MongoQuery($time, 'leads', $array, $conditions);
        $dataToday = $queryMongo->queryMongo();

        $time = $this->buildTimestamp('yesterday');
        $queryMongo = new MongoQuery($time, 'leads', $array, $conditions);
        $dataYesterday = $queryMongo->queryMongo();

        $array_hours_today = array();
        $array_hours_today['01'] = false;
        $array_hours_today['02'] = false;
        $array_hours_today['03'] = false;
        $array_hours_today['04'] = false;
        $array_hours_today['05'] = false;
        $array_hours_today['06'] = false;
        $array_hours_today['07'] = false;
        $array_hours_today['08'] = false;
        $array_hours_today['09'] = false;
        $array_hours_today['10'] = false;
        $array_hours_today['11'] = false;
        $array_hours_today['12'] = false;
        $array_hours_today['13'] = false;
        $array_hours_today['14'] = false;
        $array_hours_today['15'] = false;
        $array_hours_today['16'] = false;
        $array_hours_today['17'] = false;
        $array_hours_today['18'] = false;
        $array_hours_today['19'] = false;
        $array_hours_today['20'] = false;
        $array_hours_today['21'] = false;
        $array_hours_today['22'] = false;
        $array_hours_today['23'] = false;
        $array_hours_today['24'] = false;

        $array_hours_yesterday = array();
        $array_hours_yesterday['01'] = false;
        $array_hours_yesterday['02'] = false;
        $array_hours_yesterday['03'] = false;
        $array_hours_yesterday['04'] = false;
        $array_hours_yesterday['05'] = false;
        $array_hours_yesterday['06'] = false;
        $array_hours_yesterday['07'] = false;
        $array_hours_yesterday['08'] = false;
        $array_hours_yesterday['09'] = false;
        $array_hours_yesterday['10'] = false;
        $array_hours_yesterday['11'] = false;
        $array_hours_yesterday['12'] = false;
        $array_hours_yesterday['13'] = false;
        $array_hours_yesterday['14'] = false;
        $array_hours_yesterday['15'] = false;
        $array_hours_yesterday['16'] = false;
        $array_hours_yesterday['17'] = false;
        $array_hours_yesterday['18'] = false;
        $array_hours_yesterday['19'] = false;
        $array_hours_yesterday['20'] = false;
        $array_hours_yesterday['21'] = false;
        $array_hours_yesterday['22'] = false;
        $array_hours_yesterday['23'] = false;
        $array_hours_yesterday['24'] = false;

        foreach ($array_hours_today as $key => $hours) {
            foreach ($dataToday as $today) {
                if ((int)$today['hour'] == (int)$key) {
                    $array_hours_today[$key] = $today['Leads'];
                }
            }
        }

        foreach ($array_hours_today as $key => $hours) {
            if ($hours == false) {
                $array_hours_today[$key] = 0;
            }
        }


        foreach ($array_hours_yesterday as $key => $hours) {
            foreach ($dataYesterday as $yesterday) {
                if ((int)$yesterday['hour'] == (int)$key) {
                    $array_hours_yesterday[$key] = $yesterday['Leads'];
                }
            }
        }

        foreach ($array_hours_yesterday as $key => $hours) {
            if ($hours == false) {
                $array_hours_yesterday[$key] = 0;
            }
        }

        $data_array = array();
        $data_array['today'] = $array_hours_today;
        $data_array['yesterday'] = $array_hours_yesterday;

        $mongo = new MongoDBObjects($this->collection, $this->filter);

        $update = array();
        $update['GraphHourlyDashboard'] = json_encode($data_array);

        // echo '<pre>' . print_r($data_array, true) . '</pre>';

        $mongo->update($update);
    }
}
