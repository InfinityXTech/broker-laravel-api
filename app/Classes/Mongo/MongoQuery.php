<?php

namespace App\Classes\Mongo;

use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoDBObjects;

class MongoQuery
{


    //parameterArray should be as follow
    //(['nametogroup'] => 'val') or
    //(['nametogroup'] => 'sum')
    //(['nametogroup'] => 'count')
    // val will show the data as is and sum will show aggregated data
    // for the sum we can add the parameter currency as per of this example
    //(['nametogroup'] => 'sum','currency' => 'USD') or
    //(['nametogroup'] => 'sum','currency' => 'EUR')


    //general construct have the option of order by here is example of order by
    //array('parameter_name' => 'ASC') or array('parameter_name' => 'DESC')
    //parameter name have to be one of the parameters mentioned above on the parameterArray

    // condition will be built as follow
    // array('parameter' => 'parameter_name','value' => 'value_here') in this example the class will act as this is a single condition
    // to create multiple condition per one parameter we will use the following example
    // array('parameter' => 'parameter_name','multiCondition'=>true,'values' => array([0 => 'condition A'],[0 => 'condition B'],[0 => 'condition C']))

    protected $time_range;
    protected $collection;
    protected $parameterArray;
    protected $orderby;
    protected $conditions;

    public function __construct($time_range, $collection, $parameterArray, $conditions, $orderby = null)
    {
        $this->time_range = $time_range;
        $this->collection = $collection;
        $this->orderby = $orderby;
        $this->parameterArray = $parameterArray;
        $this->conditions = $conditions;
    }

    public function createSchema()
    {
        $schema = array();
        foreach ($this->parameterArray as $a) {
            foreach ($a as $va => $vr) {
                if ($vr == 'val') {
                    $schema[] = $va;
                }
            }
        }

        return $schema;
    }

    public function createpivot()
    {
        $pivot = array();
        foreach ($this->parameterArray as $a => $d) {
            foreach ($d as $va => $vr) {
                $name = (is_numeric($a) ? $va : $a);
                if ($vr == 'sum') {
                    //$pivot[$va] = $vr;
                    $pivot[$name] = ['field' => $va, 'data' => $vr];
                    //$pivot[$va] = ['field' => $va, 'data' => $vr];
                } elseif ($vr == 'count') {
                    //$pivot[$va] = $vr;
                    $pivot[$name] = ['field' => $va, 'data' => $vr];
                    //$pivot[$va] = ['field' => $va, 'data' => $vr];
                } elseif (is_array($vr)) {
                    //$pivot[$va] = $vr;
                    $pivot[$name] = ['field' => $va, 'data' => $vr];
                    //$pivot[$va] = ['field' => $va, 'data' => $vr];
                }
            }
        }

        return $pivot;
    }

    protected function getProjectionFields(&$pivot, &$schema)
    {
        $projection = [];
        foreach ($pivot as $p => $v) {
            if (isset($v['field'])) {
                $projection[$v['field']] = 1;
            }
            if (isset($v['data']) && isset($v['data']['formula'])) {
                $matches = [];
                preg_match_all('|__(\((.*?)\))?(.*?)__|', $v['data']['formula'], $matches);
                if (isset($matches) && count($matches) == 4) {
                    foreach ($matches[3] as $f) {
                        $projection[$f] = 1;
                    }
                }
            }
        }

        foreach ($schema as $f) {
            $projection[$f] = 1;
        }

        return $projection;
    }

    public function queryMongo($args = [])
    {

        $pivot = $this->createpivot();
        $schema = $this->createSchema();

        $where = $this->routeConditions();

        $projection = $this->getProjectionFields($pivot, $schema);

        $args_query = [];

        if (isset($args['projection'])) {
            foreach ($args['projection'] as $f => $v) {
                $projection[$f] = 1;
            }
        }

        if (count($projection) > 0) {
            $args_query['projection'] = $projection;
        }

        $hook_cell_data = null;
        if (isset($args['hook_cell_data'])) {
            $hook_cell_data = $args['hook_cell_data'];
        }

        if (is_array($this->collection)) {
            $data = [];
            foreach ($this->collection as $collection) {
                $mongo = new MongoDBObjects($collection, $where);
                $d = $mongo->findMany($args_query);
                foreach ($d as &$f) {
                    $f['collection'] = $collection;
                }
                $data = array_merge($data, $d);
            }
        } else {
            $mongo = new MongoDBObjects($this->collection, $where);
            $data = $mongo->findMany($args_query);
        }

        // hook
        if ($hook_cell_data != null) {
            for ($i = 0; $i < count($data); $i++) {
                $hook_cell_data($data[$i]);
            }
        }

        // hook
        if (isset($args['hook_data'])) {
            $args['hook_data']($data);
        }

        $result = $this->buildData($data, $pivot, $schema);

        $break = $this->breaktoschema($result, $schema);

        // return $break;
        if (count($args) == 0) {
            return $break;
        } else {
            $r = ['result' => $break];
            if (in_array('data', $args) || array_key_exists('data', $args)) {
                $r['data'] = $data;
            }
            if (in_array('where', $args) ||  array_key_exists('where', $args)) {
                $r['where'] = $where;
            }
            return $r;
        }
    }

    public function breaktoschema(&$result, &$schema)
    {
        $array = array();
        $key_divider = '1@@kk@@1';
        $counter = $schema;
        foreach ($result as $v => $d) {
            $explode = explode($key_divider, $v);
            $data = array();
            $num = 0;
            foreach ($schema as $s) {
                $data[$s] = $explode[$num];
                $num = $num + 1;
            }

            foreach ($d as $vr => $p) {
                $data[$vr] = $p;
            }

            $array[] = $data;
        }

        return $array;
    }

    public function buildData(&$data, &$pivot, &$schema)
    {

        $array = array();
        $count = count($schema);
        $key_divider = '1@@kk@@1';
        foreach ($data as $result) {
            $token = '';
            $num = 0;
            foreach ($schema as $t) {
                $num = $num + 1;
                if ($num >= $count) {
                    $token .= ($result[$t] ?? ''); //strtolower
                } else {
                    $token .= ($result[$t] ?? '') . '' . $key_divider; //strtolower
                }
            }

            foreach ($pivot as $_n => $_p) {

                $n = $_n;
                $f = $_p['field'];
                $p = $_p['data'];

                if ($p == 'count') {
                    if (isset($array[$token][$n])) {
                        $array[$token][$n] = $array[$token][$n] + ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                    } else {
                        $array[$token][$n] = ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                    }
                } elseif (is_array($p)) {
                    if ($p['type'] == 'count') {
                        if (isset($p['or'])) {
                            if (isset($result[$p['where']]) && $result[$p['where']] == $p['value'] || isset($result[$p['or']['where']]) && $result[$p['or']['where']] == $p['or']['value']) {
                                if (isset($array[$token][$n])) {
                                    $array[$token][$n] = $array[$token][$n] + ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                                } else {
                                    $array[$token][$n] = ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                                }
                            } else {
                                if (!isset($array[$token][$n])) {
                                    $array[$token][$n] = 0;
                                }
                            }
                        } elseif (isset($p['where']) && is_array($p['where'])) {
                            if ($this->is_where_logic_true($p['where'], $result)) {
                                if (isset($array[$token][$n])) {
                                    $array[$token][$n] = $array[$token][$n] + ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                                } else {
                                    $array[$token][$n] = ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                                }
                            } else {
                                if (!isset($array[$token][$n])) {
                                    $array[$token][$n] = 0;
                                }
                            }
                        } else {
                            if (isset($p['where']) && isset($result[$p['where']])) {
                                if (isset($result[$p['where']]) and  $result[$p['where']] == $p['value']) {
                                    if (isset($array[$token][$n])) {
                                        $array[$token][$n] = $array[$token][$n] + ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                                    } else {
                                        $array[$token][$n] = ($this->get_cell($f, $p, $result, 'count') ? 1 : 0); //1;
                                    }
                                } else {
                                    if (!isset($array[$token][$n])) {
                                        $array[$token][$n] = 0;
                                    }
                                }
                            } else {
                                $v = 0;
                                //if (isset($p['formula']) && !empty($p['formula'])) {
                                $v = ($this->get_cell($f, $p, $result, 'count') ? 1 : 0);
                                //}
                                if (isset($array[$token][$n])) {
                                    $array[$token][$n] = $array[$token][$n] + $v; //1;
                                } else {
                                    $array[$token][$n] = $v; //1;
                                }
                            }
                        }
                    } elseif ($p['type'] == 'sum') {
                        if (isset($p['or'])) {
                            if (isset($result[$p['where']]) && $result[$p['where']] == $p['value'] || isset($result[$p['or']['where']]) && $result[$p['or']['where']] == $p['or']['value']) {
                                if (isset($array[$token][$n])) {
                                    $array[$token][$n] = $array[$token][$n] + $this->get_cell($f, $p, $result); //(int)$result[$f];// (int)$result[$n];
                                } else {
                                    $array[$token][$n] = $this->get_cell($f, $p, $result); //(int)$result[$f];//(int)$result[$n];
                                }
                            } else {
                                if (!isset($array[$token][$n])) {
                                    $array[$token][$n] = 0;
                                }
                            }
                        } elseif (isset($p['where']) && is_array($p['where'])) {
                            if ($this->is_where_logic_true($p['where'], $result)) {
                                if (isset($array[$token][$n])) {
                                    $array[$token][$n] = $array[$token][$n] + $this->get_cell($f, $p, $result); // (int)$result[$f];//(int)$result[$n];
                                } else {
                                    $array[$token][$n] = (int)$result[$f]; //(int)$result[$n];
                                }
                            } else {
                                if (!isset($array[$token][$n])) {
                                    $array[$token][$n] = 0;
                                }
                            }
                        } else {
                            if (@isset($result[$p['where']])) {
                                if ($result[$p['where']] == $p['value']) {
                                    if (isset($array[$token][$n])) {
                                        $array[$token][$n] = $array[$token][$n] + $this->get_cell($f, $p, $result); //(int)$result[$f];//(int)$result[$n];
                                    } else {
                                        $array[$token][$n] = $this->get_cell($f, $p, $result); //(int)$result[$f];//(int)$result[$n];
                                    }
                                } else {
                                    if (!isset($array[$token][$n])) {
                                        $array[$token][$n] = 0;
                                    }
                                }
                            } else {
                                //$v = 0;
                                //if (isset($p['formula']) && !empty($p['formula'])) {
                                $v = $this->get_cell($f, $p, $result);
                                //}
                                if (isset($array[$token][$n])) {
                                    // echo ' | ' . $array[$token][$n] . ' = ' . $array[$token][$n] . ' + ' .  $v;
                                    $array[$token][$n] = $array[$token][$n] + $v; //(int)$result[$f];//(int)$result[$n];
                                } else {
                                    // echo ' | ' . $array[$token][$n] . ' = ' . $v;
                                    $array[$token][$n] = $v; //(int)$result[$f];//(int)$result[$n];
                                }
                            }
                        }
                    }
                } else {
                    if (isset($array[$token][$n])) {
                        $array[$token][$n] = $array[$token][$n] + $this->get_cell($f, $p, $result); //(int)$result[$f];//(int)$result[$n];
                    } else {
                        $array[$token][$n] = $this->get_cell($f, $p, $result); //(int)$result[$f];//(int)$result[$n];
                    }
                }
            }
        }

        return $array;
    }

    private function get_cell($f, $p, $result, $type = 'value')
    {
        $t = (isset($p['field_type']) ? $p['field_type'] : 'int');
        //echo $f.'('.$t . ') = ' . $result[$f];
        $convert = function ($v, $t, $s = false) {
            // echo gettype($v).'='.get_class($v).' '. print_r($v, true).' ||| ';

            if (isset($v) && $v instanceof \MongoDB\BSON\UTCDateTime) {
                $mil = ((array)$v)['milliseconds'];
                $seconds = $mil / 1000;
                $v = $seconds;
                //$timestamp = 
            }

            switch ($t) {
                case 'int':
                    return (int)$v;
                case 'float':
                    return (float)$v;
                case 'bool': {
                        if ($s)
                            return ($v ? 'TRUE' : 'FALSE');
                        else
                            return ($v ? TRUE : FALSE);
                    }
                case 'string': {
                        return "'" . $v . "'";
                    }
                case 'date': {
                        return date("d-m-Y", $v);
                    }
                case 'datetime': {
                        return date("d-m-Y H:i:s", $v);
                    }
            }
            return $v;
        };
        $formula_get_value = function ($f, $result, $t) use ($convert) {

            preg_match('/\(([^\)]+)\)([^$]+)/', $f, $matches);
            if ($matches && count($matches) > 1) {
                $t = $matches[1];
                $f = $matches[2];
            }

            if (!isset($result[$f])) {
                return 'NULL';
            }

            return $convert($result[$f], $t, true);
        };
        if (isset($p['formula']) && !empty($p['formula'])) {

            $formula_result = MongoQueryEval::Exec($p['formula'], $result, $t);
            // ----------------- Old Formulas -----------------
            // $formula_str = preg_replace_callback(
            //     '|__(.*?)__|',
            //     function ($matches) use ($result, $t, $formula_get_value) {
            //         $f = $matches[1];
            //         return $formula_get_value($f, $result, $t) . ' /*[' . $f . ']*/';
            //     },
            //     $p['formula']
            // );
            // $pre = (isset($p['formula_return']) && $p['formula_return'] == false ? '' : 'return ');
            // // echo 'eval('.$pre . $formula_str . (empty($pre)?'':';').')';
            // $formula_result = eval($pre . $formula_str . (empty($pre) ? '' : ';'));
            // ----------------- Old Formulas -----------------

            // echo '{{{' . $formula_result . '}}}';
            $cv = $convert($formula_result, $t);
            // echo '{{{' . $cv . '}}}';
            return $cv;
        }

        if ($type == 'count') {
            return 1;
        } else {
            return $convert($result[$f], $t);
        }
    }

    private function is_where_logic_true($w, $r)
    {
        $b = true;
        foreach ($w as $k => $i) {

            if (strtoupper($k) == 'AND' || (strtoupper($k) != 'AND' && strtoupper($k) != 'OR')) {
                $_b = true;
                foreach ($i as $f => $v) {
                    if (is_array($v)) {
                        $_b = $_b && $this->is_where_logic_true($v, $r);
                    } else {
                        $_b = $_b && (isset($r[$f]) && $r[$f] == $v);
                    }
                }
                $b = $b && $_b;
            } else
            if (strtoupper($k) == 'OR') {
                $_b = false;
                foreach ($i as $f => $v) {
                    if (is_array($v)) {
                        $_b = $_b || $this->is_where_logic_true($v, $r);
                    } else {
                        $_b = $_b || (isset($r[$f]) && $r[$f] == $v);
                    }
                }
                $b = $b && $_b;
            } // else {
            //    $b = $b && (isset($r[$k]) && $r[$k] == $i);
            //}

        }

        return $b;
    }

    public function routeConditions()
    {

        $multi = false;
        foreach ($this->conditions as $condition) {
            if (isset($condition['multiCondition']) and $condition['multiCondition'] == true) {
                $multi = true;
            } else {
            }
        }

        if ($multi == true) {
            $conditions = $this->complexSyntax();
        } else {
            $conditions = $this->regularSyntax();
        }

        $conditions = $this->strictSyntax($conditions);

        return $conditions;
    }

    public function strictSyntax($conditions)
    {
        foreach ($this->conditions as $condition) {
            if (isset($condition['strict'])) {
                if (!isset($conditions['$and'])) {
                    $conditions = [
                        '$and' => [
                            $conditions
                        ]
                    ];
                }

                $conditions['$and'][] = $condition['strict'];
            }
        }

        return $conditions;
    }

    public function regularSyntax()
    {
        $where = array();
        $Timestamp_array = array();

        if (is_array($this->time_range) && isset($this->time_range['where'])) {
            $where = $this->time_range['where'];
        } else 
        if (isset($this->time_range['start']) && isset($this->time_range['end'])) {
            $Timestamp_array[] = array('Timestamp' => array('$gte' => $this->time_range['start'], '$lte' => $this->time_range['end']));
            $Timestamp_array[] = array('depositTimestamp' => array('$gte' => $this->time_range['start'], '$lte' => $this->time_range['end']));
            $Timestamp_array[] = array('endpointDepositTimestamp' => array('$gte' => $this->time_range['start'], '$lte' => $this->time_range['end']));
            $where['$or'] = $Timestamp_array;
        }

        foreach ($this->conditions as $condition) {
            if (isset($condition['parameter_name'])) {
                $where[$condition['parameter_name']] = $condition['value'];
            }
        }

        return $where;
    }

    public function complexSyntax()
    {

        $terms = array();
        $Timestamp_array = array();

        $terms['$and'] = array();
        if (is_array($this->time_range) && isset($this->time_range['where'])) {
            $terms = $this->time_range['where'];
        } else
        if (isset($this->time_range['start']) && isset($this->time_range['end'])) {
            $Timestamp_array[] = array('Timestamp' => array('$gte' => $this->time_range['start'], '$lte' => $this->time_range['end']));
            $Timestamp_array[] = array('depositTimestamp' => array('$gte' => $this->time_range['start'], '$lte' => $this->time_range['end']));
            $Timestamp_array[] = array('endpointDepositTimestamp' => array('$gte' => $this->time_range['start'], '$lte' => $this->time_range['end']));
            $terms['$or'] = $Timestamp_array;
        }

        foreach ($this->conditions as $condition) {

            if (($condition['multiCondition'] ?? false) == true) {
                $array = array();
                foreach ($condition['values'] as $syntax) {
                    $array[][$condition['parameter_name']] = $syntax;
                }
                $terms['$and'][]['$or'] = $array;
            } elseif (isset($condition['parameter_name'])) {
                $terms[$condition['parameter_name']] = $condition['value'];
            }
        }

        return $terms;
    }
}
