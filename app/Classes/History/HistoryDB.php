<?php

namespace App\Classes\History;

use MongoDB\BSON\ObjectId;
use Illuminate\Support\Facades\Auth;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\GeneralHelper;
use Illuminate\Support\Facades\Cache;

class HistoryDB
{
    private static $instance = null;

    private static $delete_after_days = 90;

    private static $collection = 'history';

    public static function instance()
    {
        if (isset(self::$instance)) {
            return self::$instance;
        }
        self::$instance = new self();
        return self::$instance;
    }

    public function __construct()
    {
    }

    public static function clear()
    {
        $expire = strtotime('-' . self::$delete_after_days . ' days');
        $where = ['expire' => ['$lte' => new \MongoDB\BSON\UTCDateTime($expire * 1000)]];
        $mongo = new MongoDBObjects(self::$collection, $where);
        $mongo->deleteMany();
    }

    /**
     * Add to history
     *
     * @param HistoryDBAction $action
     * @param string $collection
     * @param array $new_data
     * @param string $primary_key
     * @param string $main_foreign_key
     * @param array $fields
     * @return void
     */
    public static function add(
        string $action,
        string $collection,
        array $new_data,
        string $primary_key,
        string $main_foreign_field,
        array $fields = [],
        string $category = null,
        string $description = null
    ) {

        $main_foreign_key = '';
        if (!empty($main_foreign_field)) {
            $main_foreign_key = $new_data[$main_foreign_field] ?? null;
        }

        if (isset($new_data['_id'])) {
            unset($new_data['_id']);
        }

        // prepare object data
        $insert = [];

        $insert['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime(date("Y-m-d H:i:s")) * 1000);
        $insert['action'] = $action;
        $insert['collection'] = $collection;
        $insert['primary_key'] = new ObjectId($primary_key);
        $insert['main_foreign_field'] = $main_foreign_field;
        $insert['main_foreign_key'] = (!empty($main_foreign_key) ? new ObjectId($main_foreign_key) : null);
        $insert['action_by'] = new ObjectId(Auth::id());
        $insert['data'] = [];
        $insert['diff'] = [];

        if (!empty($description)) {
            $insert['description'] = $description;
        }

        if (!empty($category)) {
            $insert['category'] = $category;
        }

        if (count($fields) == 0) {
            foreach ($new_data as $field => $value) {
                $insert['data'][$field] = $value;
            }
        } else {
            foreach ($fields as $field) {
                if (isset($new_data[$field])) {
                    $insert['data'][$field] = $new_data[$field];
                }
            }
        }

        if (count($insert['data']) == 0) {
            return;
        }

        $cache_key = 'prev_record_history_' . $primary_key;

        if ($action == 'UPDATE') {

            // get origin data
            $where = ['_id' => new ObjectId($primary_key)];
            $mongo = new MongoDBObjects($collection, $where);
            $projection = array_reduce($fields, function ($c, $f) {
                $c ??= [];
                $c[$f] = 1;
            }) ?? ['_id' => 1];
            $origin_data = $mongo->find(['projection' => $projection]) ?? [];

            // check different
            if (!empty($origin_data)) {
                $a = md5(trim(json_encode($new_data ?? [])));
                $b = md5(trim(json_encode($origin_data ?? [])));
                if ($a == $b) {
                    return;
                }
            }

            // get prev record
            $prev_record = ['data' => []];
            if (Cache::has($cache_key)) {
                $prev_record = Cache::get($cache_key);
                $insert['previous'] = new ObjectId($prev_record['_id']);
            } else {
                $where = ['primary_key' => new ObjectId($primary_key)];
                $mongo = new MongoDBObjects(self::$collection, $where);
                $prev_record_key = $mongo->find([
                    'sort' => ['timestamp' => -1],
                    'projection' => ['_id' => 1],
                    // 'limit' => 1
                ]);
                if (isset($prev_record_key) && isset($prev_record_key['_id'])) {
                    $prev_record_id = (array)$prev_record_key['_id'];
                    $prev_record_id = $prev_record_id['oid'];
                    $where = ['_id' => new ObjectId($prev_record_id)];
                    $mongo = new MongoDBObjects(self::$collection, $where);
                    $prev_record = $mongo->find();
                    if ($prev_record != null) {
                        $insert['previous'] = new ObjectId($prev_record_id);
                    }
                }
            }

            // diff
            if (!isset($prev_record) || (isset($prev_record) && !is_array($prev_record))) {

                $where = ['_id' => new ObjectId($primary_key)];
                $mongo = new MongoDBObjects($collection, $where);
                $find = $mongo->find();

                $data = [];
                if (isset($find) && count($find) > 0) {
                    if (count($fields) == 0) {
                        foreach ($find as $field => $value) {
                            $data[$field] = $value;
                        }
                    } else {
                        foreach ($fields as $field) {
                            if (isset($new_data[$field])) {
                                $data[$field] = $new_data[$field];
                            }
                        }
                    }
                }

                $prev_record = ['data' => $data];
            }

            // $diff = [];

            // --- Old CRM version ---
            // $prepare_diff = function ($arr) {
            //     $diff_arr = [];
            //     foreach ((array)$arr as $k => $v) {
            //         if (is_array($v) || $v instanceof \MongoDB\Model\BSONArray || $v instanceof \MongoDB\Model\BSONDocument) {
            //             $diff_arr[$k] = (array)$v;
            //         } else {
            //             $diff_arr[$k] = $v;
            //         }
            //     }
            //     return $diff_arr;
            // };
            // $diff = array_diff_assoc($prepare_diff((array)$insert['data']), $prepare_diff((array)$prev_record['data']));
            // --- / Old CRM version ---

            $prepare_diff = function ($arr) {
                $prepared = [];
                foreach ((array)$arr as $k => $v) {
                    $prepared[$k] = json_encode($v);
                }
                return $prepared;
            };
            $diff = array_diff_assoc($prepare_diff((array)$insert['data']), $prepare_diff((array)$prev_record['data']));
            foreach ($diff as $k => &$value) {
                $value = $insert['data'][$k];
            }

            $insert['diff'] = ($diff == null ? [] : $diff);
        }

        // insert
        $mongo = new MongoDBObjects(self::$collection, $insert);
        $insert['_id'] = $mongo->insertWithToken();

        // GeneralHelper::PrintR($insert);die();

        // cache
        Cache::put($cache_key, $insert, 60 * 60 * 12);
    }
}
