<?php

namespace App\Classes\Brokers;

use App\Helpers\GeneralHelper;
use App\Classes\FormattedSchedule;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ERROR | E_PARSE | E_WARNING | E_ALL);

class DownloadCRG
{
    private $brokerId = '';

    public function __construct(string $brokerId = '')
    {
        $this->brokerId = $brokerId;
    }

    private function getFeed()
    {
        $where = [];
        if (!empty($this->brokerId)) {
            $where['_id'] = new \MongoDB\BSON\ObjectId($this->brokerId);
        }
        $mongo = new MongoDBObjects('partner', $where);
        $brokers = $mongo->findMany();

        $ids = [];
        foreach ($brokers as $broker) {
            $ids[] = (string)$broker['_id'];
        }

        $where = ['broker' => ['$in' => $ids]];
        $mongo = new MongoDBObjects('broker_crg', $where);
        $crgs = $mongo->findMany();

        foreach ($brokers as $k => $broker) {
            $id = (string)$broker['_id'];
            $brokers[$k]['crgs'] = [];
            foreach ($crgs as $crg) {
                if ($id == $crg['broker']) {
                    $blocked_schedule = (array)($crg['blocked_schedule'] ?? []);

                    $crg['crg_week'] = '';
                    $crg['crg_weekends'] = '';

                    if (count($blocked_schedule) > 0) {

                        $timezone = $blocked_schedule['timezone'] ?? '';

                        if (isset($blocked_schedule['timezone'])) {
                            unset($blocked_schedule['timezone']);
                        }
                        $crg_week = new FormattedSchedule($blocked_schedule, false);
                        $crg_week_str = $crg_week->getData();
                        if (!empty($crg_week_str)) {
                            $crg['crg_week'] = $crg_week_str . (!empty($timezone) ? ', Timezone: ' . $timezone : '');
                        }

                        $crg_weekends = new FormattedSchedule($blocked_schedule, true);
                        $crg_weekends_str = $crg_weekends->getData();
                        if (!empty($crg_weekends_str)) {
                            $crg['crg_weekends'] = $crg_weekends_str . (!empty($timezone) ? ', Timezone: ' . $timezone : '');
                        }
                    } else {
                        $crg['crg_week'] = 'Work 24/5';
                        $crg['crg_weekends'] = 'Work 24/2';
                    }

                    $brokers[$k]['crgs'][] = $crg;
                }
            }
        }

        return $brokers;
    }

    public function makeCsv()
    {
        $columns = ['Broker', 'Deal Name', 'Deal Type', 'CRG Calculation Period', 'Country', 'Languages', 'Min CRG', 'CRG Week', 'CRG Weekends'];

        $html = '<table><thead>';
        foreach ($columns as $column) {
            $html .= '<th>' . $column . '</th>';
        }
        $html .= '</thead><tbody>';
        $brokers = $this->getFeed();

        $countries = GeneralHelper::countries(true);
        $languages = GeneralHelper::languages(true);

        $crg_types = [
            1 => 'Payout Deal',
            2 => 'CRG Deal',
            3 => 'Payout + CRG Deal'
        ];

        $calc_period_crg = [
            1 => 'Daily',
            2 => 'Weekly',
            3 => 'Monthly'
        ];

        foreach ($brokers as $broker) {
            $name = GeneralHelper::broker_name($broker);
            foreach ($broker['crgs'] as $crg) {
                if ($crg['status'] != 1) continue;
                if ($crg['type'] == 1) continue;

                $langs = (array)($crg['language_code'] ?? null ?: []);
                $langs = array_map(fn ($lang) => $languages[strtolower($lang)], $langs);

                $country = '';
                if (is_string($crg['country_code'])) {
                    $country = $countries[strtolower($crg['country_code'])];
                } else {
                    foreach ($crg['country_code'] ?? [] as $c) {
                        if (isset($countries[strtolower($c ?? '')])) {
                            $country .= (!empty($country) ? ', ' : '') . $countries[strtolower($c ?? '')] ?? '';
                        }
                    }
                }

                $html .= '<tr>';
                $html .= '<td>' . $name . '</td>';
                $html .= '<td>' . $crg['name'] . '</td>';
                $html .= '<td>' . $crg_types[$crg['type']] . '</td>';
                $html .= '<td>' . $calc_period_crg[$crg['calc_period_crg']] . '</td>';
                $html .= '<td>' . $country . '</td>';
                $html .= '<td>' . implode(', ', $langs) . '</td>';
                $html .= '<td>' . $crg['min_crg'] . '</td>';
                $html .= '<td>' . $crg['crg_week'] . '</td>';
                $html .= '<td>' . $crg['crg_weekends'] . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';

        return $html;
    }

    public function download()
    {
        $html = $this->makeCsv();

        $filename = 'BrokerPrice_' . date('Y-m-d/') . '.xls';

        ob_clean();

        header("Content-Description: File Transfer");
        //header("Content-Type: application/octet-stream");
        header('Content-type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header("Content-Type: application/force-download");
        header("Content-Type: application/download");
        header("Content-Length: " . strlen($html));
        //readfile($path);

        echo $html;
        exit();
    }
}
