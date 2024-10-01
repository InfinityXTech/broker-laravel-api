<?php

namespace App\Classes;

use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\GeneralHelper;
use Exception;
use MongoDB\BSON\ObjectId;

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ERROR | E_PARSE | E_WARNING | E_ALL);

class DownloadRecalculationChanges
{
    private array $leadIds = [];

    public function __construct(array $leadIds)
    {
        $this->leadIds = $leadIds;
    }

    private function getFeed()
    {
        $ids = [];
        foreach ($this->leadIds as $id) {
            $ids[] = new ObjectId($id);
        }

        // history
        $where = [
            'primary_key' => ['$in' => $ids],
            'category' =>  ['$in' => ['crg', 'broker_crg', 'cpl', 'broker_cpl']],
            'collection' => 'leads'
        ];
        $mongo = new MongoDBObjects('history', $where);
        $history_logs = $mongo->findMany([
            'sort' => ['primary_key' => 1, 'timestamp' => 1]
        ]);

        // users
        $userIds = [];
        foreach ($history_logs as $log) {
            if (!empty($log['action_by'])) {
                $userIds[] = new ObjectId((string)$log['action_by']);
            }
        }
        $where = ['_id' => ['$in' => $userIds]];
        $mongo = new MongoDBObjects('users', $where);
        $users = $mongo->findMany();

        foreach ($history_logs as &$log) {
            foreach ($users as $user) {
                if (!empty($log['action_by']) && (string)$user['_id'] == (string)$log['action_by']) {
                    $log['action_by_user_data'] = [
                        'account_email' => $user['account_email'],
                        'name' => $user['name'],
                    ];
                    break;
                }
            }
        }

        return $history_logs;
    }

    private function groupByTimestamp($history_logs)
    {
        $result = [];
        foreach ($history_logs as $log) {
            $ts = (array)$log['timestamp'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $timestamp = date("Y-m-d H:i:s", $seconds);
            $primary_key = (string)$log['primary_key'];
            $group_key = $primary_key . '_' . $timestamp;

            if (!isset($result[$group_key])) {
                $result[$group_key] = $log;
                $result[$group_key]['broker_description'] = '';
                $result[$group_key]['description'] = '';
                $result[$group_key]['broker_category'] = '';
                $result[$group_key]['category'] = '';
                // if (isset($result[$group_key]['description'])) {
                //     unset($result[$group_key]['description']);
                // }
            } else {
                foreach ($log['data'] ?? [] as $key => $value) {
                    $result[$group_key]['data'][$key] = $value;
                }
            }

            if (isset($log['data']['broker_crg_deal'])) {
                $result[$group_key]['broker_description'] .= (empty($result[$group_key]['description']) ? '' : ', ') . ($log['description'] ?? '');
                $result[$group_key]['broker_category'] = $log['category'] ?? '';
            } else
            if (isset($log['data']['crg_deal'])) {
                $result[$group_key]['description'] .= (empty($result[$group_key]['description']) ? '' : ', ') . ($log['description'] ?? '');
                $result[$group_key]['category'] = $log['category'] ?? '';
            } else
            if (isset($log['data']['broker_cpl'])) {
                $result[$group_key]['broker_description'] = (empty($result[$group_key]['description']) ? '' : ', ') . ($log['description'] ?? '');
                $result[$group_key]['broker_category'] = $log['category'] ?? '';
            } else
            if (isset($log['data']['isCPL'])) {
                $result[$group_key]['description'] .= (empty($result[$group_key]['description']) ? '' : ', ') . ($log['description'] ?? '');
                $result[$group_key]['category'] = $log['category'] ?? '';
            }
        }
        return $result;
    }

    private function get_reason($description)
    {
        if (!empty($description)) {
            $s = '';
            try {
                $data = json_decode($description, true);
                if ($data) {
                    if (isset($data['broker'])) {
                        foreach ($data['broker'] ?? [] as $m) {
                            if (!empty($m['message'])) {
                                $s .= (empty($s) ? '' : ', ') . $m['message'];
                            }
                        }
                    }
                    if (isset($data['endpoint'])) {
                        foreach ($data['endpoint'] ?? [] as $m) {
                            if (!empty($m['message'])) {
                                $s .= (empty($s) ? '' : ', ') . $m['message'];
                            }
                        }
                    }
                }
            } catch (Exception $ex) {
            }
            if (!empty($s)) {
                return $s;
            } else {
                return $description;
            }
        }
        return '';
    }

    public function makeCsv()
    {
        $columns = ['Lead Id', 'Timestamp', 'User', 'Endpoint Category', 'Endpoint', 'Endpoint Reason', 'Broker Category', 'Broker', 'Broker Reason'];

        $html = '<table><thead>';
        foreach ($columns as $column) {
            $html .= '<th>' . $column . '</th>';
        }
        $html .= '</thead><tbody>';
        $_history_logs = $this->getFeed();
        $history_logs = $this->groupByTimestamp($_history_logs);
        // GeneralHelper::PrintR($_history_logs);
        // die();

        foreach ($history_logs as $group_key => $log) {

            $action_by = 'system';

            if (isset($log['action_by_user_data'])) {
                $action_by = $log['action_by_user_data']['account_email']; // ['name]
            }

            $crg_deal = (isset($log['data']['crg_deal']) ? ($log['data']['crg_deal'] ? 'On' : 'Off') : '');
            $broker_crg_deal = (isset($log['data']['broker_crg_deal']) ? ($log['data']['broker_crg_deal'] ? 'On' : 'Off') : '');

            $cpl = (isset($log['data']['isCPL']) ? ($log['data']['isCPL'] ? 'On' : 'Off') : '');
            $broker_cpl = (isset($log['data']['broker_cpl']) ? ($log['data']['broker_cpl'] ? 'On' : 'Off') : '');

            // $reason = !empty($crg_deal) ? $this->get_reason($log['description'] ?? '') : '';
            // $broker_reason = !empty($broker_crg_deal) ? $this->get_reason($log['description'] ?? '') : '';

            $reason = $this->get_reason($log['description'] ?? '');
            $broker_reason = $this->get_reason($log['broker_description'] ?? '');

            $cat = $log['category'] ?? '';
            $broker_cat = $log['broker_category'] ?? '';

            $active = '';
            $category = '';
            if ($cat == 'cpl') {
                $category = 'CPL';
                $active = $cpl;
            } else if ($cat == 'crg') {
                $category = 'CRG';
                $active = $crg_deal;
            }

            $broker_active = '';
            $broker_category = '';
            if ($broker_cat == 'broker_cpl') {
                $broker_category = 'CPL';
                $broker_active = $broker_cpl;
            } else if ($broker_cat == 'broker_crg') {
                $broker_category = 'CRG';
                $broker_active = $broker_crg_deal;
            }

            $ts = (array)$log['timestamp'];
            $mil = $ts['milliseconds'];
            $seconds = $mil / 1000;
            $timestamp = date("Y-m-d H:i:s", $seconds);

            $html .= '<tr>';
            $html .= '<td>' . (string)$log['primary_key'] . '</td>';
            $html .= '<td>' . $timestamp . '</td>';
            $html .= '<td>' . $action_by . '</td>';
            $html .= '<td>' . $category . '</td>';
            $html .= '<td>' . $active . '</td>';
            $html .= '<td>' . $reason . '</td>';
            $html .= '<td>' . $broker_category . '</td>';
            $html .= '<td>' . $broker_active . '</td>';
            $html .= '<td>' . $broker_reason . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    public function download()
    {
        $html = $this->makeCsv();

        $filename = 'CRG_Changes_' . date('Y-m-d/') . '.xls';

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
