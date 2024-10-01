<?php

namespace App\Classes\Eloquent;

use Dotenv\Parser\Value;
use App\Helpers\CryptHelper;
use App\Helpers\GeneralHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class EncryptionEloquentBuilder extends Builder
{
    // private $real_collection = '';
    // private $collection = '';
    private $columns = [];
    private $exclude_crypt_fields = [];
    private $enabled_rename_meta = false;
    private $enable_crypt_database = false;

    public function __construct(QueryBuilder $query, string $real_collection)
    {
        // $this->real_collection = $this->collection = $real_collection;
        $this->enable_crypt_database = config('crypt.database.enable', false);
        $this->enabled_rename_meta = config('crypt.database.rename_meta', false);
        if ($this->enable_crypt_database || $this->enabled_rename_meta) {
            // GeneralHelper::PrintR(['crypt.database.scheme.' . $collection]);die();
            $scheme = config('crypt.database.scheme.' . $real_collection, []);
            // $this->collection = $scheme['collection'] ?? $real_collection;
            $this->columns = $scheme['fields'] ?? [];
            $this->exclude_crypt_fields = $scheme['exclude_crypt_fields'] ?? [];
        }
        parent::__construct($query);
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        if (is_array($query) && !in_array('*', $query)) {
            foreach ($query as &$column) {
                if (is_string($column) && !empty($column)) {
                    if ($this->is_allow_rename($column)) {
                        $column = $this->columns[$column];
                    }
                }
            }
        }
        return parent::select($query, $bindings, $useReadPdo);
    }

    /**
     * is_allow_rename function
     *
     * @param [type] $column
     * @return boolean
     */
    private function is_allow_rename($column): bool
    {
        if (
            $this->enabled_rename_meta === true &&
            is_string($column) &&
            !empty($column) &&
            !in_array($column, ['_id', 'clientId']) &&
            isset($this->columns[$column])
        ) {
            return true;
        }
        return false;
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
            $this->enable_crypt_database === true &&
            !in_array($column, $this->exclude_crypt_fields) &&
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
     * if enabled crypt data - crypt only string
     *
     * @param mixed $value
     * @return mixed
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
     * Execute the query as a "select" statement.
     *
     * @param  array|string  $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $columns ??= ['*'];
        if (!isset($columns['*'])) {
            if (isset($columns['*']) || empty($columns)) {
                foreach ($this->columns as $convention => $actual) {
                    if ($this->is_allow_rename($convention)) {
                        $columns[$actual] = $this->columns[$convention];
                    }
                }
            } else {
                foreach ($columns as &$column) {
                    if ($this->is_allow_rename($column)) {
                        $column = $this->columns[$column];
                    }
                }
            }
        }
        // GeneralHelper::PrintR([$this->columns, $columns]);die();
        return parent::get($columns);
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  \Closure|string|array|\Illuminate\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (!is_callable($column)) {
            $operators = [
                '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
                'like', 'like binary', 'not like', 'ilike',
                '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
                'rlike', 'not rlike', 'regexp', 'not regexp',
                '~', '~*', '!~', '!~*', 'similar to',
                'not similar to', 'not ilike', '~~*', '!~~*',
            ];

            if (is_null($value)) {
                $is_operator = !is_null($operator) && is_string($operator) && !empty($operator) && in_array(strtolower($operator), $operators, true);
                if (!$is_operator) {
                    $value = (string)$operator;
                    $operator = '=';
                    // GeneralHelper::PrintR([$column, $operator, $value]);die();
                }
            }

            if (is_string($column) && !empty($column)) {
                $value = $this->encrypt($column, $value);
                if ($this->is_allow_rename($column)) {
                    $column = $this->columns[$column];
                }
                //GeneralHelper::PrintR([$column, $value]);die();
                // Log::info('$column: ' . $column . ' $value: ' . $value);
            } else if (is_array($column)) {
                foreach ($column as $c => $v) {
                    if ($this->is_allow_rename($c)) {
                        $column[$this->columns[$c]] = $this->encrypt($c, $v);
                        unset($column[$c]);
                    }
                }
            }
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        // GeneralHelper::PrintR([$column, $this->columns]);die();
        if (!in_array($column, $this->exclude_crypt_fields)) {
            foreach ($values as &$value) {
                $value = $this->encrypt($column, $value);
            }
        }
        if ($this->is_allow_rename($column)) {
            $column = $this->columns[$column];
        }
        return parent::whereIn($column, $values, $boolean, $not);
    }

    public function whereRaw($filter)
    {
        if (is_array($filter) && !empty($filter)) {
            foreach ($filter as $column => $v) {
                if ($this->is_allow_rename($column)) {
                    $renamed_column = $this->columns[$column];
                    $filter[$renamed_column] = $this->encrypt($column, $v);
                    unset($filter[$column]);
                }
            }
        }
        return parent::whereRaw($filter);
    }

    protected function addUpdatedAtColumn(array $values)
    {
        return $values;
    }
}
