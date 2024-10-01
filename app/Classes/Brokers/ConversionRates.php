<?php

namespace App\Classes\Brokers;

use App\Helpers\QueryHelper;
use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoQuery;
use Illuminate\Support\Facades\Gate;

class ConversionRates
{
    private int $days_ago_count = 30;
    private string $brokerId;

    private $start;
    private $end;

    public function __construct(string $brokerId)
    {
        $this->brokerId = $brokerId;
    }

    public function collect(): array
    {
        $header = [];
        for ($i = 0; $i <= $this->days_ago_count; $i++) {
            $header[] = [
                'date' => date('j', strtotime('-' . $i . 'days')),
                'title' => $this->dayIndexTitle($i),
            ];
        }

        $countries = GeneralHelper::countries();

        $data = $this->getConversionRates();

        $maxCr = 0;
        foreach ($data as $datas) {
            foreach ($datas as $item) {
                $maxCr = max($maxCr, $item['cr']);
            }
        }

        $rates = [];
        foreach ($data as $country_code => $datas) {
            $rate = [
                'country_code' => $country_code,
                'country_name' => $countries[strtolower($country_code)],
            ];
            for ($i = 0; $i <= $this->days_ago_count; $i++) {
                $rate['day' . $i] = [
                    'title' => $this->dayIndexTitle($i),
                    'depositors' => $datas[$i]['depositors'] ?? 0,
                    'leads' => $datas[$i]['leads'] ?? 0,
                    'color' => $this->getColorIndex($datas[$i]['cr'] ?? 0, $maxCr),
                    'cr' => round($datas[$i]['cr'] ?? 0, 2),
                ];
            }
            $rates[] = $rate;
        }
        return [
            'header' => $header,
            'rates' => $rates,
        ];
    }

    private function getColorIndex($value, $max)
    {
        return max(0, min(255, 255 - (int)(255 * $value / ($max > 0 ? $max : 1))));
    }

    private function dayIndexTitle($i)
    {
        switch ($i) {
            case 0:
                return date('d.m.Y', strtotime('-' . $i . 'days')) . ': Today';
            case 1:
                return date('d.m.Y', strtotime('-' . $i . 'days')) . ': Yesterday';
            default:
                return date('d.m.Y', strtotime('-' . $i . 'days')) . ': ' . $i . ' days ago';
        }
    }

    public function getConversionRates()
    {
        $condition = QueryHelper::buildConditions([
            'brokerId' => [$this->brokerId]
        ]);
        $result = [];

        for ($i = 0; $i <= $this->days_ago_count; $i++) {
            $time = $this->buildTimestamp($i);
            $query = $this->buildParameterArray();

            $queryMongo = new MongoQuery($time, 'leads', $query['query'], $condition);
            $list = $queryMongo->queryMongo();

            foreach ($list as $data) {
                $result[$data['country']][$i] = [
                    'depositors' => $data['Depositors'],
                    'leads' => $data['Leads'],
                    'cr' => ($data['Leads'] > 0 ? (100 * $data['Depositors'] / $data['Leads']) : 0)
                ];
            }
        }
        ksort($result);
        return $result;
    }

    private function buildTimestamp($day_offset)
    {
        $this->start = strtotime('-' . $day_offset . ' days 00:00:00');
        $this->end   = strtotime('-' . $day_offset . ' days 23:59:59');
        return [
            'start' => new \MongoDB\BSON\UTCDateTime($this->start * 1000),
            'end' => new \MongoDB\BSON\UTCDateTime($this->end * 1000),
        ];
    }

    private function buildParameterArray()
    {
        $formula = array();
        $array = array();

        $pivots = [
            'country',
        ];

        foreach ($pivots as $pivot) {
            $array[] = array($pivot => 'val');
        }

        $array['Leads'] = array('Leads' => [
            'type' => 'count',
            'formula' => '
                if ( __(bool)match_with_broker__ == TRUE  && __Timestamp__ >= ' . $this->start . ' && __Timestamp__ <= ' . $this->end . ' && __(bool)test_lead__ == FALSE) {
                    return true;
                }
                return false;
            ',
            'formula_return' => false
        ]);
        $array['Depositors'] = array('depositor' => [
            'type' => 'count',
            'formula' => '
                if (__(bool)depositor__ == TRUE &&
                    ' .
                    // TODO: Access
                    // customUserAccess::is_forbidden('deposit_disapproved')
                    (Gate::has('custom[deposit_disapproved]') && Gate::allows('custom[deposit_disapproved]') ? ' __(bool)deposit_disapproved__ == FALSE && ' : '') .
                '__depositTimestamp__ >= ' . $this->start . ' && __depositTimestamp__ <= ' . $this->end . ' && __(bool)test_lead__ == FALSE ) {
                    return true;
                }
                return false;
            ',
            'formula_return' => false
        ]);

        $db = array();
        $db['formula'] = $formula;
        $db['query'] = $array;

        return $db;
    }
}
