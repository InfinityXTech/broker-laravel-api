<?php

namespace App\Classes\Brokers;

use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\GeneralHelper;

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ERROR | E_PARSE | E_WARNING | E_ALL);

class DownloadPrice
{
    private $brokerId = '';

    public function __construct(string $brokerId = '') {
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
            $id = (array)$broker['_id'];
            $id = $id['oid'];
            //$ids[] = new MongoDB\BSON\ObjectId($id);
            $ids[] = $id;
        }

        $where = ['broker' => ['$in' => $ids]];
        $mongo = new MongoDBObjects('broker_payouts', $where);
        $payouts = $mongo->findMany();

        foreach ($brokers as $k => $broker) {
            $id = (array)$broker['_id'];
            $id = $id['oid'];
            $brokers[$k]['payouts'] = [];
            foreach ($payouts as $payout) {
                if ($id == $payout['broker']) {
                    $brokers[$k]['payouts'][] = $payout;
                }
            }
        }

        return $brokers;
    }

    public function makeCsv()
    {
        $columns = ['broker', 'country', 'language', 'price', 'type(CPL or CPA)', 'Status'];
        $html = '<table><thead>';
        foreach ($columns as $column) {
            $html .= '<th>' . $column . '</th>';
        }
        $html .= '</thead><tbody>';
        $brokers = $this->getFeed();

        $countries = GeneralHelper::countries(true);
        $languages = GeneralHelper::languages(true);

        $cost_type = [
            '1' => 'CPA',
            '2' => 'CPL',
        ];

        foreach ($brokers as $broker) {
            $name = GeneralHelper::broker_name($broker);
            foreach ($broker['payouts'] as $payout) {
                $html .= '<tr>';
                $html .= '<td>' . $name . '</td>';
                $html .= '<td>' . $countries[strtolower($payout['country_code'])] . '</td>';
                $html .= '<td>' . (empty($payout['language_code']) ? 'general' : $languages[strtolower($payout['language_code'])]) . '</td>';
                $html .= '<td>' . $payout['payout'] . '</td>';
                $html .= '<td>' . $cost_type[$payout['cost_type']] . '</td>';
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