<?php

namespace App\Classes\TrafficEndpoints;

use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\GeneralHelper;

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ERROR | E_PARSE | E_WARNING | E_ALL);

class DownloadPrice
{
    private $traffic_endpoint_id = '';

    public function __construct(string $traffic_endpoint_id = '') {
        $this->traffic_endpoint_id = $traffic_endpoint_id;
    }

    private function getFeed()
    {
        $where = [];
        if (!empty($this->traffic_endpoint_id)) {
            $where['_id'] = new \MongoDB\BSON\ObjectId($this->traffic_endpoint_id);
        }
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $Endpoints = $mongo->findMany();

        $ids = [];
        foreach ($Endpoints as $Endpoint) {
            $id = (array)$Endpoint['_id'];
            $id = $id['oid'];
            //$ids[] = new MongoDB\BSON\ObjectId($id);
            $ids[] = $id;
        }

        $where = ['TrafficEndpoint' => ['$in' => $ids]];
        $mongo = new MongoDBObjects('endpoint_payouts', $where);
        $payouts = $mongo->findMany();

        foreach ($Endpoints as $k => $Endpoint) {
            $id = (array)$Endpoint['_id'];
            $id = $id['oid'];
            $Endpoints[$k]['payouts'] = [];
            foreach ($payouts as $payout) {
                if ($id == $payout['TrafficEndpoint']) {
                    $Endpoints[$k]['payouts'][] = $payout;
                }
            }
        }

        return $Endpoints;
    }

    public function makeCsv()
    {
        $columns = ['Endpoint', 'country', 'language', 'price', 'type(CPL or CPA)', 'Status'];
        $html = '<table><thead>';
        foreach ($columns as $column) {
            $html .= '<th>' . $column . '</th>';
        }
        $html .= '</thead><tbody>';
        $traffic_endpoints = $this->getFeed();

        $countries = GeneralHelper::countries(true);
        $languages = GeneralHelper::languages(true);

        $cost_type = [
            '1' => 'CPA',
            '2' => 'CPL',
        ];

        foreach ($traffic_endpoints as $traffic_endpoint) {
            $name = ($traffic_endpoint['token'] ?? '');
            foreach ($traffic_endpoint['payouts'] as $payout) {				
				$html .= '<tr>';
                $html .= '<td>' . $name . '</td>';
                $html .= '<td>' . (!empty($payout['country_code']) && isset($countries[strtolower($payout['country_code'])]) ? $countries[strtolower($payout['country_code'])] : '') . '</td>';
                $html .= '<td>' . (empty($payout['language_code']) ? 'general' : $languages[strtolower($payout['language_code'])] ?? '') . '</td>';
                $html .= '<td>' . ($payout['payout'] ?? '') . '</td>';
                $html .= '<td>' . ($cost_type[$payout['cost_type'] ?? ''] ?? '') . '</td>';
				$html .= '<td>' . (($payout['enabled'] ?? false) ? 'Active' : 'Inactive') . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';

        return $html;
    }

    public function download()
    {
        $html = $this->makeCsv();

        $filename = 'TrafficEndpointPrice_' . date('Y-m-d/') . '.xls';

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