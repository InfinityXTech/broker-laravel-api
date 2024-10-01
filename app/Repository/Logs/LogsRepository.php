<?php

namespace App\Repository\Logs;

use App\Repository\BaseRepository;

use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Logs\ILogsRepository;

class LogsRepository extends BaseRepository implements ILogsRepository
{

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct()
    {
    }

    private function fetch_users()
    {
        $where = [];
        $mongo = new MongoDBObjects('users', $where);
        $data = $mongo->findMany();

        $users = [];
        foreach ($data as $user) {
            $id = (array)$user['_id'];
            $id = $id['oid'];
            $users[$id] = $user;
        }

        return $users;
    }

    private function get_decorated_diff($old, $new)
    {
        $from_start = strspn($old ^ $new, "\0");
        $from_end = strspn(strrev($old) ^ strrev($new), "\0");

        $old_end = strlen($old) - $from_end;
        $new_end = strlen($new) - $from_end;

        $start = substr($new, 0, $from_start);
        $end = substr($new, $new_end);
        $new_diff = substr($new, $from_start, $new_end - $from_start);
        $old_diff = substr($old, $from_start, $old_end - $from_start);

        $new = "$start<ins style='background-color:#ccffcc'>$new_diff</ins>$end";
        $old = "$start<del style='background-color:#ffcccc'>$old_diff</del>$end";
        return array("old" => $old, "new" => $new);
    }

    public function get_log(int $page): array
    {
        // $page = $payload['page'] ?? 1;
        $mongo = new MongoDBObjects('history', []);
        $find = $mongo->findMany([
            'skip' => ($page - 1) * 10,
            'limit' => 10,
            'sort'  => ['$natural' => -1],
        ]);

        $result = [];

        $users = $this->fetch_users();

        $timezone = new \DateTimeZone('Asia/Famagusta');

        $prev = [];
        foreach ($find as $item) {
            $id = (array)$item['_id'];
            $id = $id['oid'];

            $item['timestamp'] = (isset($item['timestamp']) ? $item['timestamp']->toDateTime()->setTimeZone($timezone)->format('d-m-Y H:i:s') : '');

            $json = json_encode($item['data']);

            $key = $item['collection'] . '_' . $item['primary_key'];

            if (isset($prev[$key]) && count($prev[$key]) > 0) {
                $diff = [];
                $prev_json = end($prev[$key]);
                $diff = $this->get_decorated_diff($prev_json, $json);

                $json = $diff['new'];
            }

            $item['json'] = $json;

            $item['action_by'] = strval($item['action_by']);
            $item['primary_key'] = strval($item['primary_key']);
            $item['main_foreign_key'] = strval($item['main_foreign_key']);

            $item['action_by'] = isset($users[$item['action_by']]) ? ($users[$item['action_by']]['account_email'] ?? '') : '';

            $result[] = $item;
        }

        return $result;
    }
}
