<?php

namespace App\Classes\Mongo;

use \MongoDB\Client;
use App\Helpers\CryptHelper;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Classes\History\HistoryDB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Classes\History\HistoryDBAction;

class MongoDBObjects
{

    /**
     * Database Name
     *
     * @var string
     */
    public string $dbname;

    /**
     * Collection Name
     *
     * @var string
     */
    private string $collection_name;

    /**
     * Collection object
     *
     * @var [type]
     */
    private $collection;

    /**
     * Client of connection
     *
     * @var Client
     */
    private Client $client;

    /**
     * ConnectionString
     *
     * @var string
     */
    private string $connection_string;

    /**
     * INSERT object if action is insert, else WHERE
     *
     * @var array
     */
    private array $array;

    /**
     * Client ID
     *
     * @var string
     */
    private string $clientId = '';

    // cache
    private bool $enable_cache = false;
    private array $cache_collections = [];

    // history of changes
    private bool $enable_history = false;
    private array $history_collections = [];

    // crypt
    private bool $enabled_crypt = false;
    private bool $enabled_rename_meta = false;
    private array $columns = [];
    private array $exclude_crypt_fields = [];

    /**
     * Action Name
     *
     * @var string
     */
    private string $action = '';

    /**
     * Write log if result more then x records
     *
     * @var null|integer
     */
    private $warning_if_more_x_rows = null;

    public function __construct(string $collection, array $array = [])
    {
        $default = config('database.default');
        $connections = config('database.connections');

        $this->connection_string = $connections[$default]['dsn'];

        // cache
        $this->enable_cache = false; //$config['db']['cache'] && $config['cache']['enable'];
        $this->cache_collections = []; //$config['db']['cache_collections'];

        $this->warning_if_more_x_rows = $connections[$default]['warning_if_more_x_rows'] ?? null;

        // history
        $connections_log = config('database-log.connections');
        $this->enable_history = $connections_log[$default]['enable'] ?? false;
        $this->history_collections = $connections_log[$default]['collections'] ?? [];

        // rename / crypt
        $this->enabled_crypt = config('crypt.database.enable', false);
        $this->enabled_rename_meta = config('crypt.database.rename_meta', false);
        if ($this->enabled_crypt === true || $this->enabled_rename_meta === true) {
            $scheme = config('crypt.database.scheme.' . $collection, []);
            $this->columns = $scheme['fields'] ?? [];
            $this->exclude_crypt_fields = $scheme['exclude_crypt_fields'] ?? [];
            $array = $this->map($array);
            if ($this->enabled_rename_meta === true && !empty($scheme['collection'])) {
                $this->collection_name = $collection = $scheme['collection'];
            }
        }

        $this->client = new Client($this->connection_string);
        $this->dbname = 'test';
        if (!empty($connections[$default]['database'] ?? '')) {
            $this->dbname = $connections[$default]['database'];
        }

        $this->array = $array;
        $this->collection_name = $collection;
        $this->collection = $this->client->{$this->dbname}->{$collection};
    }

    /**
     * is_allow_crypt function
     *
     * @param [type] $value
     * @return boolean
     */
    private function is_allow_crypt($column, $value): bool
    {
        if (
            $this->enabled_crypt === true &&
            !in_array($column, $this->exclude_crypt_fields) &&
            array_key_exists($column, $this->columns) &&
            is_string($value) &&
            !empty($value) &&
            !is_numeric($value) &&
            !is_bool($value) &&
            !preg_match('/^[a-f\d]{24}$/i', $value)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Encrypt
     *
     * @param [type] $value
     * @return [type]
     */
    private function encrypt($column, $value)
    {
        if ($this->is_allow_crypt($column, $value)) {
            $encrypted_value = CryptHelper::encrypt($value);
            if (!empty($encrypted_value)) {
                return $encrypted_value;
            }
        }
        return $value;
    }

    /**
     * if enabled crypt data - decrypt only string
     *
     * @param mixed $value
     * @return mixed
     */
    private function decrypt($value)
    {
        if ($this->enabled_crypt === true && is_string($value) && !empty($value) && !preg_match('/^[a-f\d]{24}$/i', $value)) {
            $decrypted_value = CryptHelper::decrypt($value);
            if (!empty($decrypted_value)) {
                return $decrypted_value;
            }
        }
        return $value;
    }

    /**
     * Check ignore field for rename
     *
     * @param string $field
     * @return boolean
     */
    private function is_ignore_map(string $field): bool
    {
        return in_array($field, ['_id', 'clientId']);
    }

    /**
     * Is array associative or sequential?
     *
     * @param array $attributes
     * @return boolean
     */
    private function is_array_indexed(array $attributes): bool
    {
        return array_values($attributes) === $attributes;
    }

    /**
     * if enabled rename - map data
     *
     * @param array $attributes
     * @return array
     */
    private function map(array $attributes, array $args = []): array
    {
        if (!$this->enabled_rename_meta) {
            return $attributes;
        }

        if ($this->is_array_indexed($attributes)) {
            $is_bulk = false;
            foreach ($attributes as &$item) {

                if (is_array($item)) {

                    // is bulk
                    $key = array_key_first($item) ?? '';
                    if (in_array($key, ['insertOne', 'updateOne', 'updateMany', 'replaceOne', 'deleteOne', 'deleteMany'])) {
                        $is_bulk = true;
                        if (isset($item[$key][0])) {
                            $item[$key][0] = $this->map($item[$key][0]); // where
                        }
                        if (isset($item[$key][1])) {
                            foreach ($item[$key][1] as $operation => &$values) {
                                $values = $this->map($values);
                            }
                        }
                    } else {
                        $item = $this->map($item);
                    }
                }
            }

            // if ($is_bulk) {
            return $attributes;
            // }
        }

        // if ($this->collection_name == 'leads' || $this->collection_name == 'm55') {
        //     GeneralHelper::PrintR($attributes);die();
        // }
        // $or, $and
        $is_or_and_condition = false;
        foreach ($attributes as $operation => &$value) {
            switch ($operation) {
                case '$or':
                case '$and': {
                        foreach ($value as &$v) {
                            $v = $this->map($v);
                        }
                        $is_or_and_condition = true;
                        break;
                    }
            }
        }

        // if ($is_or_and_condition) {
        //     return $attributes;
        // }

        // check variables with $
        $is_variables = false;
        foreach ($attributes as $operation => &$value) {
            if (is_string($value) && !empty($value)) {
                if ($value[0] == '$') {
                    $field = substr($value, 1);
                    if (isset($this->columns[$field])) {
                        $is_variables = true;
                        $value =  '$' . $this->columns[$field];
                    }
                }
            } else if (is_array($value)) {
                foreach ($value as $operation => &$v) {
                    if (is_array($v) && !empty($v)) {
                        $v = $this->map($v);
                    } else {
                        switch ($operation) {
                            case '$sum':
                            case '$tan':
                            case '$min':
                            case '$max':
                            case '$avg':
                            case '$toObjectId':
                            case '$toUpper':
                            case '$toLower':
                            case '$toString':
                            case '$toBool':
                            case '$toDouble':
                            case '$toLong':
                            case '$toInt': {
                                    if (is_string($v) && !empty($v)) {
                                        if ($v[0] == '$') {
                                            $field = substr($v, 1);
                                            if (isset($this->columns[$field])) {
                                                $is_variables = true;
                                                $v =  '$' . $this->columns[$field];
                                            }
                                        }
                                    }
                                    break;
                                }
                            default: {
                                    if (is_string($v) && !empty($v)) {
                                        if ($v[0] == '$') {
                                            $field = substr($v, 1);
                                            if (isset($this->columns[$field])) {
                                                $is_variables = true;
                                                $v =  '$' . $this->columns[$field];
                                            }
                                        }
                                    }
                                }
                        }
                    }
                }
            }
        }

        if ($is_variables) {
            return $attributes;
        }

        $parseField = function (string $attribute) {
            $markDollar = strpos($attribute, '$') !== false && ($attribute[0] ?? '') == '$';
            $markDot = strpos($attribute, '.') !== false;
            if ($markDollar || $markDot) {
                $_attribute = explode('.', $attribute)[0] ?? $attribute;
                if ($markDollar) {
                    $_attribute = substr($_attribute, 1);
                }
                $end = $markDot ? substr($attribute, strpos($attribute, '.') + 1) : '';
                return [
                    'success' => true,
                    'attribute' => $_attribute,
                    'mark_dollar' => $markDollar,
                    'mark_dot' => $markDot,
                    'field' => $attribute,
                    'end' => $end
                ];
            }
            return [
                'success' => false,
                'attribute' => $attribute,
                'mark_dollar' => false,
                'mark_dot' => false,
                'field' => $attribute,
                'end' => ''
            ];
        };

        // prepare data if we have sub query, example: field.sub_field = 1
        $_attributes = [];
        foreach (array_keys($attributes) as $attribute) {
            if (is_string($attribute) && !empty($attribute)) {
                $p = $parseField($attribute);
                if ($p['success'] && ($p['mark_dollar'] || $p['mark_dot'])) {
                    $_attributes[$p['attribute']] = $p;
                }
            }
        }

        // make rename and crypt
        foreach ($this->columns as $convention => $actual) {
            if (
                (array_key_exists($convention, $attributes) ||
                    array_key_exists($convention, $_attributes)
                ) &&
                (!$this->is_ignore_map($convention)
                )
            ) {

                if (array_key_exists($convention, $_attributes)) {
                    $p = $_attributes[$convention];
                    $actual = ($p['mark_dollar'] ? '$' : '') . $actual . ($p['mark_dot'] ? '.' . $p['end'] : '');
                    $convention = $p['field'];
                }

                $attributes[$actual] = $attributes[$convention];
                unset($attributes[$convention]);

                // check cond
                $is_cond = false;
                if (is_array($attributes[$actual])) {
                    foreach ($attributes[$actual] as $operation => &$value) {
                        if ($operation == '$cond') {
                            $h = function ($v) use (&$h, $convention, $parseField) {
                                if (is_string($v) && !empty($v)) {
                                    $p = $parseField($v);
                                    if ($p['success'] && ($p['mark_dollar'] || $p['mark_dot'])) {
                                        $v = ($p['mark_dollar'] ? '$' : '') . (isset($this->columns[$p['attribute']]) ? $this->columns[$p['attribute']] : $p['attribute']) . ($p['mark_dot'] ? '.' . $p['end'] : '');
                                    }
                                } else if (is_array($v) && !empty($v)) {
                                    if ($this->is_array_indexed($v)) {
                                        foreach ($v as &$vv) {
                                            $vv = $h($vv);
                                        }
                                    } else {
                                        foreach ($v as $operation => &$vv) {
                                            switch ($operation) {
                                                case '$and':
                                                case '$or': {
                                                        $vv = $h($vv);
                                                        break;
                                                    }
                                                case '$eq':
                                                case '$ne':
                                                case '$lt':
                                                case '$lte':
                                                case '$gt':
                                                case '$gte': {
                                                        if (count($vv) > 1) {
                                                            $p = $parseField($vv[0]);
                                                            if ($p['success'] && ($p['mark_dollar'] || $p['mark_dot'])) {
                                                                $vv[0] = ($p['mark_dollar'] ? '$' : '') . (isset($this->columns[$p['attribute']]) ? $this->columns[$p['attribute']] : $p['attribute']) . ($p['mark_dot'] ? '.' . $p['end'] : '');
                                                            } else if (isset($this->columns[$p['attribute']])) {
                                                                $vv[0] = $this->columns[$p['attribute']];
                                                            }
                                                            if ($p['mark_dot'] == false && !in_array($convention, $this->exclude_crypt_fields)) {
                                                                $vv[1] = $this->encrypt($convention, $vv[1]);
                                                            }
                                                            break;
                                                        }
                                                    }
                                                case '$nin':
                                                case '$in': {
                                                        if (count($vv) > 1) {
                                                            $p = $parseField($vv[0]);
                                                            if ($p['success'] && ($p['mark_dollar'] || $p['mark_dot'])) {
                                                                $vv[0] = ($p['mark_dollar'] ? '$' : '') . (isset($this->columns[$p['attribute']]) ? $this->columns[$p['attribute']] : $p['attribute']) . ($p['mark_dot'] ? '.' . $p['end'] : '');
                                                            } else if (isset($this->columns[$p['attribute']])) {
                                                                $vv[0] = $this->columns[$p['attribute']];
                                                            }
                                                            if ($p['mark_dot'] == false && !in_array($convention, $this->exclude_crypt_fields)) {
                                                                if (is_array($vv[1])) {
                                                                    foreach ($vv[1] as &$vvv) {
                                                                        $vvv = $this->encrypt($convention, $vvv);
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        break;
                                                    }
                                            }
                                        }
                                    }
                                }
                                return $v;
                            };

                            foreach ($value as $operation => &$v) {
                                switch ($operation) {
                                    case 'if':
                                    case 'then':
                                    case 'else': {
                                            $is_cond = true;
                                            $v = $h($v);
                                            break;
                                        }
                                }
                            }
                            // print_r($value);
                        }
                    }
                }

                if ($is_cond == false && ($args['crypt'] ?? true) == true && strpos($actual, '.') === false) {
                    if (is_array($attributes[$actual])) {
                        foreach ($attributes[$actual] as $operation => &$value) {
                            switch ($operation) {
                                case '$nin':
                                case '$in': {
                                        if (!in_array($convention, $this->exclude_crypt_fields)) {
                                            foreach ($value as &$v) {
                                                $v = $this->encrypt($convention, $v);
                                            }
                                        }
                                        break;
                                    }
                                case '$eq':
                                case '$ne':
                                case '$lt':
                                case '$lte':
                                case '$gt':
                                case '$gte': {
                                        if (!in_array($convention, $this->exclude_crypt_fields)) {
                                            $value = $this->encrypt($convention, $value);
                                        }
                                        break;
                                    }
                            }
                        }
                    } else if (!in_array($convention, $this->exclude_crypt_fields)) {
                        $attributes[$actual] = $this->encrypt($convention, $attributes[$actual]);
                    }
                }
            }
        }

        // print_r($attributes);
        return $attributes;
    }

    /**
     * if enabled rename - unmup data to real name
     *
     * @param array $attributes
     * @return array
     */
    private function unmap(array $attributes): array
    {
        if (!$this->enabled_rename_meta) {
            return $attributes;
        }

        if ($this->is_array_indexed($attributes)) {
            foreach ($attributes as &$item) {
                $item = $this->unmap($item);
            }
        } else {
            foreach ($this->columns as $convention => $actual) {
                if (
                    array_key_exists($actual, $attributes) &&
                    !$this->is_ignore_map($actual)
                ) {
                    if ($this->enabled_rename_meta) {
                        $attributes[$convention] = $attributes[$actual];
                        if (!in_array($convention, $this->exclude_crypt_fields)) {
                            $attributes[$convention] = $this->decrypt($attributes[$convention]);
                        }
                        unset($attributes[$actual]);
                    } else {
                        $attributes[$actual] = $attributes[$actual];
                        if (!in_array($convention, $this->exclude_crypt_fields)) {
                            $attributes[$actual] = $this->decrypt($attributes[$actual]);
                        }
                    }
                }
            }
        }
        return $attributes;
    }

    /**
     * Check maximum rows ant write log if more than X
     *
     * @param integer $count_rows
     * @return void
     */
    private function check_rows(int $count_rows): void
    {
        try {
            if ($this->warning_if_more_x_rows > 0 && $count_rows > $this->warning_if_more_x_rows) {
                Log::warning('Fetched more than ' . $this->warning_if_more_x_rows . ' rows. Collection: ' . $this->collection_name . ', Count: ' . $count_rows);
            }
        } catch (\Exception $ex) {
        }
    }

    /**
     * Set ClientId
     *
     * @param string $client_id
     * @return void
     */
    public function set_client_id(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * disable auto attach clientId
     *
     * @return void
     */
    public function without_client_id()
    {
        $this->clientId = '-1';
    }

    /**
     * Attach clientId if not exist in array
     *
     * @return void
     */
    private function attach_query_client(): void
    {
        if (empty($this->array['clientId'])) { //!in_array($this->collection_name, ['broker_integrations', '']
            $this->array['clientId'] = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();
            if ($this->array['clientId'] == '-1') {
                unset($this->array['clientId']);
            }
        }
    }

    /**
     * Get ID of record
     *
     * @param array|null $id
     * @param string $default
     * @return string
     */
    public static function get_id(?array $id, string $default = ''): string
    {
        if (isset($id) && is_array($id)) {
            if (isset($id['_id'])) {
                $id = (array)$id['_id'];
                return $id['oid'];
            }
            if (isset($id['oid'])) {
                return $id['oid'];
            }
        }
        return $default;
    }

    /**
     * Get Timestamp
     *
     * @param array $timestamp
     * @param string $format
     * @return string|integer
     */
    public static function get_timestamp(?array $timestamp, string $format = '')
    {
        $result = (!empty($format) ? '' : 0);
        $ts = (array)($timestamp ?? []);
        if (isset($ts['milliseconds'])) {
            $mil = $ts['milliseconds'];
            $result = $mil / 1000; // seconds
            if (!empty($format)) {
                $result = date($format, $result); //"Y-m-d H:i:s"
            }
        }
        return $result;
    }

    /**
     * Get cache key
     *
     * @param array $ext
     * @return string
     */
    private function get_cache_key(array $ext = []): string
    {
        $cache_ext = '';
        if (count($this->array)) {
            $cache_ext .= md5(serialize($this->array));
        }
        if (count($ext)) {
            $cache_ext .= md5($cache_ext . serialize($this->array));
        }
        $cache_key = $this->collection_name . (!empty($cache_ext) ? '_' . $cache_ext : '');
        return $cache_key;
    }

    /**
     * Get Data from cache
     *
     * @param array $ext
     * @return void
     */
    private function get_cache(array $ext = [])
    {
        if ($this->enable_cache && isset($this->cache_collections[$this->collection_name])) {
            $cache_key = $this->get_cache_key($ext);
            if (Cache::has($cache_key)) {
                $data = Cache::get($cache_key);
                $this->set_cache($data, $ext);
                return $data;
            }
        }
        return false;
    }

    /**
     * Set data to cache
     *
     * @param array $data
     * @param array $ext
     * @return void
     */
    private function set_cache(array $data, array $ext = []): void
    {
        if ($this->enable_cache && isset($this->cache_collections[$this->collection_name])) {
            $cache_key = $this->get_cache_key($ext);
            $options = $this->cache_collections[$this->collection_name];
            Cache::put($cache_key, $data, $options['ttl']);

            $cache_keys_name = $this->collection_name . '_keys';
            $cache_keys = [];
            if (Cache::has($cache_keys_name)) {
                $cache_keys = Cache::get($cache_keys_name);
            }
            if (!in_array($cache_key, $cache_keys)) {
                $cache_keys[] = $cache_key;
            }
            Cache::put($cache_keys_name, $cache_keys, $options['ttl']);
        }
    }

    /**
     * Clear data cache
     *
     * @return void
     */
    private function clear_cache(): void
    {
        if ($this->enable_cache && isset($this->cache_collections[$this->collection_name])) {
            $cache_key = $this->collection_name . '_keys';
            $cache_keys = [];
            if (Cache::has($cache_key)) {
                $cache_keys = Cache::get($cache_key);
            }
            foreach ($cache_keys as $cache_key) {
                if (Cache::has($cache_key)) {
                    Cache::put($cache_key, -51);
                }
            }
            Cache::flush();
            $cache_key = $this->collection_name . '_keys';
            $options = $this->cache_collections[$this->collection_name];
            Cache::put($cache_key, [], $options['ttl']);
        }
    }

    /**
     * Is disabled cache?
     *
     * @return boolean
     */
    private function is_enable_history(): bool
    {
        return $this->enable_history && isset($this->history_collections[$this->collection_name]);
    }

    /**
     * Add to history of changes
     *
     * @param string $action
     * @param string $id
     * @param array $data
     * @param array|null $prev_data
     * @return void
     */
    private function add_to_history(string $action, string $id, array $data, array $prev_data = null): void
    {
        if ($this->is_enable_history()) {
            $params = $this->history_collections[$this->collection_name];
            $main_foreign_field = isset($params['main_foreign_field']) ? $params['main_foreign_field'] : '';
            $fields = isset($params['fields']) ? $params['fields'] : [];

            if (!empty($main_foreign_field) && !isset($data[$main_foreign_field])) {
                $data[$main_foreign_field] = $prev_data[$main_foreign_field] ?? null;
            }
            HistoryDB::add($action, $this->collection_name, $data, $id, $main_foreign_field, $fields);
        }
    }

    /**
     * Inc or Dec value
     *
     * @param array $update
     * @return boolean
     */
    public function findOneAndUpdate(array $update)
    {
        $this->action = 'FIND_ONE_AND_UPDATE';

        $update = $this->map($update);

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        if ($this->is_enable_history()) {
            $data = $this->find();
            $ar = (array)$data['_id'];
            $id = $ar['oid'];
            $this->add_to_history(HistoryDBAction::Update, $id, $update, $data);
        }

        if ($this->collection->findOneAndUpdate($this->array, ['$inc' => $update])) {
            $this->clear_cache();
            return true;
        }
        return false;
    }

    /**
     * Delete One row by query
     *
     * @return boolean
     */
    public function deleteOne(): bool
    {
        $this->action = 'DELETE_ONE';
        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        if ($this->is_enable_history()) {
            $data = $this->find();
            $ar = (array)$data['_id'];
            $id = $ar['oid'];
            $this->add_to_history(HistoryDBAction::Delete, $id, $data);
        }

        $insertOneResult = $this->collection->deleteOne($this->array);

        if ($insertOneResult > 0) {
            $this->clear_cache();
            return true;
        }
        return false;
    }

    /**
     * Delete by query
     *
     * @return boolean
     */
    public function deleteMany(): bool
    {
        $this->action = 'DELETE_MANY';

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        if ($this->is_enable_history()) {
            $data = $this->findMany();
            for ($i = 0; $i < count($data); $i++) {
                $ar = (array)$data[$i]['_id'];
                $id = $ar['oid'];
                $this->add_to_history(HistoryDBAction::Delete, $id, $data[$i]);
            }
        }

        $insertOneResult = $this->collection->deleteMany($this->array);

        if ($insertOneResult > 0) {
            $this->clear_cache();
            return true;
        }
        return false;
    }

    /**
     * Update and insert if not exists
     *
     * @param [type] $replace
     * @param boolean $unset
     * @return boolean
     */
    public function update(array $update, bool $unset = false): bool
    {
        $this->action = 'UPDATE_UPSERT';

        $update = $this->map($update);

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        if ($this->is_enable_history()) {
            $data = $this->find();
            $ar = (array)$data['_id'];
            $id = $ar['oid'];
            $this->add_to_history(HistoryDBAction::Update, $id, $update, $data);
        }

        if ($this->collection->updateOne($this->array, [$unset ? '$unset' : '$set' => $update])) {
            $this->clear_cache();
            return true;
        }
        return false;
    }

    /**
     * Update Many Rows by query
     *
     * @param array $update
     * @param boolean $unset
     * @return boolean
     */
    public function updateMulti(array $update, bool $unset = false)
    {
        $this->action = 'UPDATE_MANY';

        $update = $this->map($update);

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        if ($this->is_enable_history()) {
            $data = $this->findMany();
            for ($i = 0; $i < count($data); $i++) {
                $ar = (array)$data[$i]['_id'];
                $id = $ar['oid'];
                $this->add_to_history(HistoryDBAction::Update, $id, $update, $data[$i]);
            }
        }

        if ($this->collection->updateMany($this->array, [$unset ? '$unset' : '$set' => $update], array('multiple' => true))) {
            $this->clear_cache();
            return true;
        }
        return false;
    }

    /**
     * Update and insert if not exists
     *
     * @param array $update
     * @return boolean
     */
    public function update_upsert(array $update): bool
    {

        $this->action = 'UPDATE_UPSERT';

        $update = $this->map($update);

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        if ($this->is_enable_history()) {
            $data = $this->findMany();
            for ($i = 0; $i < count($data); $i++) {
                $ar = (array)$data[$i]['_id'];
                $id = $ar['oid'];
                $this->add_to_history(HistoryDBAction::Update, $id, $update, $data[$i]);
            }
        }

        if ($this->collection->updateOne($this->array, ['$set' => $update], array('upsert' => true))) {
            $this->clear_cache();
            return true;
        }
        return false;
    }

    /**
     * Insert to mongo
     *
     * @return boolean
     */
    public function insert(): bool
    {
        $this->action = 'INSERT';

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        $insertOneResult = $this->collection->insertOne($this->array);
        $counter = (int)$insertOneResult->getInsertedCount();
        if ($counter > 0) {

            $this->clear_cache();

            if ($this->is_enable_history()) {
                $ar = (array)$insertOneResult->getInsertedId();
                $id = $ar['oid'];
                $this->add_to_history(HistoryDBAction::Insert, $id, $this->array);
            }
            return true;
        }
        return false;
    }

    /**
     * Insert with _id
     *
     * @return false|string
     */
    public function insertWithToken()
    {
        $this->action = 'INSERT';

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        $insertOneResult = $this->collection->insertOne($this->array);
        $counter = (int)$insertOneResult->getInsertedCount();
        if ($counter > 0) {

            $this->clear_cache();

            $ar = (array)$insertOneResult->getInsertedId();
            $id = $ar['oid'];

            if ($this->is_enable_history()) {
                $this->add_to_history(HistoryDBAction::Insert, $id, $this->array);
            }

            return $id;
        }
        return false;
    }

    /**
     * Insert many rows
     *
     * @return false|array
     */
    public function insertMany()
    {
        $this->action = 'INSERT_MANY';

        foreach ($this->array as &$arr) {
            if (empty($arr['clientId'] ?? '')) {
                $arr['clientId'] = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();
            }
        }

        $insertOneResult = $this->collection->insertMany($this->array);
        $counter = (int)$insertOneResult->getInsertedCount();
        if ($counter > 0) {

            $this->clear_cache();
            $ars = (array)$insertOneResult->getInsertedIds();

            if ($this->is_enable_history()) {
                for ($i = 0; $i < count($ars); $i++) {
                    $id = $ars[$i]['oid'];
                    $this->add_to_history(HistoryDBAction::Insert, $id, $this->array[$i]);
                }
            }

            return $ars;
        }
        return false;
    }

    /**
     * Bulk Insert | Update | Delete
     *
     * @return boolean
     */
    public function bulkwrite(): bool
    {
        $this->action = 'BULK_WRITE';

        $options = ['ordered' => false];

        if ($this->collection->bulkWrite($this->array, $options)) {

            $this->clear_cache();

            if ($this->is_enable_history()) {
                foreach ($this->array as $bulk_action => $bulk_data) {
                    $action = $bulk_action;
                    $where = $bulk_data[0];
                    // $update = $$bulk_data[1]['$set'];

                    switch ($action) {
                        case 'updateOne': {
                                $mongo = new MongoDBObjects($this->collection_name, $where);
                                $data = $mongo->find();
                                $ar = (array)$data['_id'];
                                $id = $ar['oid'];
                                $this->add_to_history(HistoryDBAction::Update, $id, $data);
                                break;
                            }
                        case 'updateMany': {
                                break;
                            }
                        case 'insertOne': {
                                break;
                            }
                        case 'deleteOne': {
                                break;
                            }
                        case 'deleteMany': {
                                break;
                            }
                        case 'replaceOne': {
                                break;
                            }
                    }
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Find One row
     *
     * @param array $options
     * @return null|array
     */
    public function find(array $options = [])
    {
        $this->action = 'FIND';

        if ($result = $this->get_cache($options)) {
            return $result;
        }

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        foreach ($options as $operation => &$value) {
            switch ($operation) {
                case 'sort':
                case 'projection': {
                        $value = $this->map($value, ['crypt' => false]);
                        break;
                    }
            }
        }

        $cursor = $this->collection->findOne($this->array, $options);

        $data = (array)$cursor;
        $array[] = $data;

        $result = $this->unmap($array[0]);

        $this->set_cache($result);

        return $result;
    }

    /**
     * Find many rows by query
     *
     * @param array $options
     * @return array
     */
    public function findMany(array $options = []): array
    {
        $this->action = 'FIND_MANY';

        // $options = [];
        // if ($sort != null) {
        //     $options['sort'] = $sort;
        // }

        if ($result = $this->get_cache($options)) {
            return $result;
        }

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        foreach ($options as $operation => &$value) {
            switch ($operation) {
                case 'sort':
                case 'projection': {
                        $value = $this->map($value, ['crypt' => false]);
                        break;
                    }
            }
        }
// if ($this->collection_name == 'leads' || $this->collection_name == 'm55') {
//     GeneralHelper::PrintR($this->array);die();
// }
        $cursor = $this->collection->find($this->array, $options);

        $array = array();
        foreach ($cursor as $restaurant) {
            $data = (array)$restaurant;
            $array[] = $this->unmap($data);
        }

        $this->check_rows(count($array));

        $this->set_cache($array, $options);

        return $array;
    }

    /**
     * Aggregate
     *
     * @param array $options
     * @param boolean $single
     * @param boolean $unset_id
     * @return null|array
     */
    public function aggregate(array $options, bool $single = false, bool $unset_id = true)
    {

        $this->action = 'AGREGATE';

        $pipeline = [];

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        if (isset($options['pipeline'])) {
            $pipeline = $options['pipeline'];
        } else {
            if (count($this->array) > 0) {
                $pipeline[] = ['$match' => $this->array];
            } else
            if (isset($options['match'])) {
                if (!isset($options['match']['clientId']) && is_array($options['match'])) {
                    $clientId = !empty($this->clientId) ? $this->clientId : ClientHelper::clientId();
                    if (is_array($clientId)) {
                        $options['match']['clientId'] = ['$in' => $clientId];
                    } else {
                        $options['match']['clientId'] = $clientId;
                    }
                }
                $pipeline[] = ['$match' => $options['match']];
            }

            if (isset($options['project'])) {
                $pipeline[] = ['$project' => $options['project']];
            }

            if (isset($options['unwind'])) {
                $pipeline[] = ['$unwind' => $options['unwind']];
            }

            if (isset($options['group'])) {
                if (!isset($options['group']['_id'])) {
                    $options['group']['_id'] = null;
                }
                $pipeline[] = ['$group' => $options['group']];
            }

            if (isset($options['sort'])) {
                $pipeline[] = ['$sort' => $options['sort']];
            }

            if (isset($options['limit'])) {
                $pipeline[] = ['$limit' => $options['limit']];
            }
        }

        foreach ($pipeline as &$operations) {
            foreach ($operations as $operation => &$value) {
                switch ($operation) {
                    case '$sort':
                    case '$project': {
                            $value = $this->map($value, ['crypt' => false, 'operation' => $operation]);
                            break;
                        }
                    case '$addFields':
                    case '$group':
                    case '$match': {
                            $value = $this->map($value, ['operation' => $operation]);
                            break;
                        }
                }
            }
        }

        $cursor = $this->collection->aggregate($pipeline, ["useCursor" => true, "batchSize" => 2]);

        $result = [];
        foreach ($cursor as $r) {
            $data = (array)$r;
            if ($unset_id && isset($data['_id'])) {
                unset($data['_id']);
            }
            $result[] = $data;
        }
        $return = null;
        if ($single) {
            $return = count($result) > 0 ? $this->unmap($result[0] ?? []) : null;
        } else {
            $return = $this->unmap(count($result) > 0 ? $result : []);
            if (isset($return)) {
                foreach ($return ?? [] as &$item) {
                    $item = $this->unmap($item);
                }
            }
        }
        return $return;
    }

    /**
     * Count of rows
     *
     * @return integer
     */
    public function count(): int
    {
        $this->action = 'COUNT';

        if (!isset($this->array['clientId'])) {
            $this->attach_query_client();
        }

        $args = ['projection' => ['_id' => 1]];
        $cursor = $this->collection->find($this->array, $args);
        return count(iterator_to_array($cursor));
    }
}
