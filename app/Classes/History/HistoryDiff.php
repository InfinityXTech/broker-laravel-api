<?php

namespace App\Classes\History;

use App\Helpers\RenderHelper;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use Closure;
use Exception;

class HistoryDiff
{
    private $next;
    private $prev;

    private ?array $broker_names = null;
    private ?array $endpoint_names = null;
    private ?array $integration_names = null;

    public function init($next, $prev, $use_diff_field = false)
    {
        if ($use_diff_field) {
            $this->next = (array)($next['diff'] ?? []);
            $this->prev = [];
        } else {
            $next['data'] = (array)($next['data'] ?? []);
            $prev['data'] = (array)($prev['data'] ?? []);

            switch ($next['action']) {
                case 'INSERT':
                    $this->next = $next['data'];
                    $this->prev = [];
                    break;
                case 'DELETE':
                    $this->next = [];
                    $this->prev = $next['data'];
                    break;
                case 'UPDATE':
                    if ($prev != null) {
                        $this->next = $next['data'];
                        $this->prev = $prev['data'];
                    } else {
                        $this->next = $next['data']; //[];
                        $this->prev = [];
                    }
                    break;
            }
        }

        $this->next = json_decode(json_encode((array)($this->next ?? [])), true);
        $this->prev = json_decode(json_encode((array)($this->prev ?? [])), true);
        if (!is_array($this->next)) {
            $this->next = [];
        }
        if (!is_array($this->prev)) {
            $this->prev = [];
        }

        if (count($this->next) == 1 && !$this->is_assoc($this->next)) {
            $this->next = $this->next[0];
        }

        if (count($this->prev) == 1 && !$this->is_assoc($this->prev)) {
            $this->prev = $this->prev[0];
        }
    }

    private function is_assoc(array $array): bool
    {
        $keys = array_keys($array);
        return $keys !== array_keys($keys);
    }

    public function value($field, $title, $show_value = true)
    {
        if (!array_key_exists($field, $this->next)) {
            return '';
        }
        $next = $this->next[$field] ?? ''; // '__UNSET__';
        $prev = $this->prev[$field] ?? ''; //'__UNSET__';
        if ($next instanceof \MongoDB\BSON\UTCDateTime) {
            $value = RenderHelper::format_datetime($next, 'Y-m-d H:i:s');
        } else {
            try {
                $value = json_encode($next, JSON_UNESCAPED_UNICODE);
            } catch (Exception $ex) {
                $value = $this->next[$field] ?? '';
            }
        }

        if ($show_value) {
            return $next = $title . ': ' . (empty($value ?? '') ? '' : $value);
            // return $next = $prev ? '' : $title . ': ' . $value;
        } else {
            return $next = 'Changed ' . $title;
        }
    }

    public function array($field, $title)
    {
        if (!isset($this->next[$field])) {
            return '';
        }
        list($add, $del) = $this->diff_arrays((array)($this->next[$field] ?? []), (array)($this->prev[$field] ?? []));
        return $this->output($title, $add, $del);
    }

    public function custom($field, $title, Closure $render)
    {
        if (!isset($this->next[$field])) {
            return '';
        }
        list($add, $del) = $this->diff_arrays($this->next[$field] ?? [], $this->prev[$field] ?? []);
        return $this->output($title, $add, $del, $render, $render);
    }

    public function endpoints($field, $title)
    {
        if (!isset($this->next[$field])) {
            return '';
        }
        list($add, $del) = $this->diff_arrays($this->next[$field] ?? [], $this->prev[$field] ?? []);
        $endpoint_names = $this->get_endpoint_names();
        $endpoint_names = function ($id) use ($endpoint_names) {
            return $endpoint_names[$id] ?? $id;
        };
        return $this->output($title, $add, $del, $endpoint_names, $endpoint_names);
    }

    public function brokers($field, $title)
    {
        if (!isset($this->next[$field])) {
            return '';
        }
        list($add, $del) = $this->diff_arrays($this->next[$field] ?? [], $this->prev[$field] ?? []);
        $broker_names = $this->get_broker_names();
        $broker_names = function ($id) use ($broker_names) {
            return $broker_names[$id] ?? $id;
        };
        return $this->output($title, $add, $del, $broker_names, $broker_names);
    }

    public function integrations($field, $title)
    {
        if (!isset($this->next[$field])) {
            return '';
        }
        list($add, $del) = $this->diff_arrays($this->next[$field] ?? [], $this->prev[$field] ?? []);
        $integration_names = $this->get_integration_names();
        $integration_names = function ($id) use ($integration_names) {
            return $integration_names[$id] ?? $id;
        };
        return $this->output($title, $add, $del, $integration_names, $integration_names);
    }

    public function endpoint_dailycaps($field, $title)
    {
        // if (!isset($this->next[$field])) {
        //     $del = [];
        //     foreach ($this->prev[$field]['endpoint'] ?? [] as $i => $id) {
        //         $del[] = $id;
        //     }
        //     if (!empty($del)) {
        //         $endpoint_names = $this->get_endpoint_names();
        //         $delFn = function ($id) use ($endpoint_names) {
        //             return $endpoint_names[$id] ?? $id;
        //         };
        //         return $this->output($title, [], $del, function () {
        //         }, $delFn);
        //     }
        //     return '';
        // }

        list($add, $del) = $this->diff_arrays($this->next[$field]['endpoint'] ?? [], $this->prev[$field]['endpoint'] ?? []);

        $caps = [];
        foreach ($this->next[$field]['endpoint'] ?? [] as $i => $id) {
            // $caps[$id] = $this->next[$field]['daily_cap'][$i];
            $caps[$id] = [
                'daily_cap' => $this->next[$field]['daily_cap'][$i],
                'sub_publisher_list' => (array)($this->next[$field]['sub_publisher_list'][$i] ?? [])
            ];
        }

        $del_caps = [];
        foreach ($this->prev[$field]['endpoint'] ?? [] as $i => $id) {
            // $caps[$id] = $this->next[$field]['daily_cap'][$i];
            $del_caps[$id] = [
                'daily_cap' => $this->prev[$field]['daily_cap'][$i],
                'sub_publisher_list' => (array)($this->prev[$field]['sub_publisher_list'][$i] ?? [])
            ];
        }

        foreach ($this->prev[$field]['endpoint'] ?? [] as $i => $id) {
            if (
                isset($caps[$id]) &&
                (
                    ($caps[$id]['daily_cap'] != ($this->prev[$field]['daily_cap'][$i] ?? null)) ||
                    (implode(',', $caps[$id]['sub_publisher_list']) != implode(',', (array)($this->prev[$field]['sub_publisher_list'][$i] ?? ['___'])))
                )
            ) {
                $add[] = $id;
            }
        }

        $endpoint_names = $this->get_endpoint_names();
        $addFn = function ($id) use ($endpoint_names, $caps) {
            return ($endpoint_names[$id] ?? $id) . '=' . $caps[$id]['daily_cap'] . ', [' . implode(', ', (array)($caps[$id]['sub_publisher_list'] ?? [])) . ']';
        };
        $delFn = function ($id) use ($endpoint_names, $del_caps) {
            return ($endpoint_names[$id] ?? $id) .
                (isset($del_caps[$id]) ? ('=' . $del_caps[$id]['daily_cap'] . ', [' . implode(', ', (array)($del_caps[$id]['sub_publisher_list'] ?? [])) . ']') : '');
        };
        return $this->output($title, $add, $del, $addFn, $delFn);
    }

    public function endpoint_dailycaps_priorities($field, $title)
    {
        if (!isset($this->next[$field])) {
            return '';
        }

        $result = '';
        foreach ($this->next[$field] as $priority => $endpoints) {

            $countries_next = (array)(($this->next[$field] ?? [])[$priority] ?? []);
            $countries_prev = (array)(($this->prev[$field] ?? [])[$priority] ?? []);

            list($add, $del) = $this->diff_arrays(array_keys($countries_next), array_keys($countries_prev));

            $endpoint_names = $this->get_endpoint_names();
            $endpoint_names_fn = function ($id) use ($endpoint_names) {
                return ($endpoint_names[$id] ?? $id);
            };

            $result_item = '';
            $addFn = function ($country) use (&$result_item, $priority, $endpoint_names_fn, $countries_next, $countries_prev) {
                list($add, $del) = $this->diff_arrays($countries_next[$country] ?? [], $countries_prev[$country] ?? []);
                $result_item .= $this->output($priority, $add, $del, $endpoint_names_fn, $endpoint_names_fn);
            };
            $delFn = function ($country) use (&$result_item, $priority, $endpoint_names_fn, $countries_next, $countries_prev) {
                list($add, $del) = $this->diff_arrays($countries_next[$country] ?? [], $countries_prev[$country] ?? []);
                $result_item .= $this->output($priority, $add, $del, $endpoint_names_fn, $endpoint_names_fn);
            };

            $result .= $this->output($title, $add, $del, $addFn, $delFn) . (!empty($result_item) ? '[' . $result_item . '] ' : '');
        }
        return $result;
    }

    public function restricted_brokers_by_country($field, $title)
    {
        if (!isset($this->next[$field])) {
            return '';
        }

        $lists = ['whitelist', 'blacklist'];

        $result = '';
        foreach ($lists as $list) {

            $countries_next = (array)(($this->next[$field] ?? [])[$list] ?? []);
            $countries_prev = (array)(($this->prev[$field] ?? [])[$list] ?? []);

            list($add, $del) = $this->diff_arrays(array_keys($countries_next), array_keys($countries_prev));

            $broker_names = $this->get_broker_names();
            $broker_name_fn = function ($id) use ($broker_names) {
                return ($broker_names[$id] ?? $id);
            };

            $result_item = '';
            $addFn = function ($country) use (&$result_item, $list, $broker_name_fn, $countries_next, $countries_prev) {
                list($add, $del) = $this->diff_arrays($countries_next[$country] ?? [], $countries_prev[$country] ?? []);
                $result_item .= $this->output($list, $add, $del, $broker_name_fn, $broker_name_fn);
            };
            $delFn = function ($country) use (&$result_item, $list, $broker_name_fn, $countries_next, $countries_prev) {
                list($add, $del) = $this->diff_arrays($countries_next[$country] ?? [], $countries_prev[$country] ?? []);
                $result_item .= $this->output($list, $add, $del, $broker_name_fn, $broker_name_fn);
            };

            $result .= $this->output($title, $add, $del, $addFn, $delFn) . (!empty($result_item) ? '[' . $result_item . '] ' : '');
        }
        return $result;
    }

    public function blocked_schedule($field, $title)
    {
        if (!isset($this->next[$field])) {
            return '';
        }
        $next = serialize($this->next[$field] ?? null);
        $prev = serialize($this->prev[$field] ?? null);
        if ($next == $prev) {
            return '';
        }
        $days = [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $result = [];

        foreach ($days as $key => $day) {
            list($add, $del) = $this->diff_arrays($this->next[$field][$key] ?? [], $this->prev[$field][$key] ?? []);
            $result[] = $this->output($day, $add, $del);
        }
        foreach ($this->next[$field] as $key => $value) {
            // GeneralHelper::PrintR($value);die();
            if (!isset($days[$key]) && $value != ($this->prev[$field][$key] ?? null)) {
                $result[] = $key . ': ' . $value;
            }
        }
        return $title . ': [' . implode(', ', array_filter($result)) . ']';
    }

    function diff_array_recursive($arr1, $arr2)
    {
        $outputDiff = [];

        foreach ($arr1 as $key => $value) {
            //if the key exists in the second array, recursively call this function
            //if it is an array, otherwise check if the value is in arr2
            if (array_key_exists($key, $arr2)) {
                if (is_array($value)) {
                    $recursiveDiff = $this->diff_array_recursive($value, $arr2[$key]);

                    if (count($recursiveDiff)) {
                        $outputDiff[$key] = $recursiveDiff;
                    }
                } else if (!in_array($value, $arr2)) {
                    $outputDiff[$key] = $value;
                }
            }
            //if the key is not in the second array, check if the value is in
            //the second array (this is a quirk of how array_diff works)
            else if (!in_array($value, $arr2)) {
                $outputDiff[$key] = $value;
            }
        }

        return $outputDiff;
    }

    private function diff_arrays($next, $prev)
    {
        $add = [];
        $del = [];

        try {
            $add = array_diff((array)($next ?? []), (array)($prev ?? []));
        } catch (Exception $ex) {
        }
        try {
            $del = array_diff((array)($prev ?? []), (array)($next ?? []));
        } catch (Exception $ex) {
        }

        // $add = $this->array_diff_recursive((array)($next ?? []), (array)($prev ?? []));
        // $del = $this->array_diff_recursive((array)($prev ?? []), (array)($next ?? []));

        return [$add, $del];
    }

    private function output($title, $add, $del, $addFn = null, $delFn = null)
    {
        $res = [];

        $add = array_filter($add, function ($item) {
            // return is_string($item) && $item !== '';
            return $item !== '';
        });
        if (!empty($add)) {
            $res[] = implode(', ', (array) ($addFn ? array_map($addFn, $add) : $add) ?? []);
        }
        $del = array_filter($del, function ($item) {
            return $item !== '';
        });
        if (!empty($del)) {
            $res[] = '<strike>' . implode(', ', $delFn ? array_map($delFn, $del) : $del) . '</strike>';
        }
        return empty($res) ? '' : $title . ': ' . implode(',', $res);
        // return $title . ': ' . (!empty($res) ? implode(', ', $res) : '');
    }

    private function get_endpoint_names()
    {
        if (!isset($this->endpoint_names)) {
            $where = [];
            $mongo = new MongoDBObjects('TrafficEndpoints', $where);
            $partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1]]);
            $result = [];
            foreach ($partners as $partner) {
                $result[MongoDBObjects::get_id($partner)] = $partner['token'] ?? '';
            }
            $this->endpoint_names = $result;
        }
        return $this->endpoint_names;
    }

    private function get_broker_names()
    {
        if (!isset($this->broker_names)) {
            $where = [];
            $mongo = new MongoDBObjects('partner', $where);
            $partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1, 'partner_name' => 1]]);
            $result = [];
            foreach ($partners as $partner) {
                $result[MongoDBObjects::get_id($partner)] = GeneralHelper::broker_name($partner);
            }
            $this->broker_names = $result;
        }
        return $this->broker_names;
    }

    private function get_integration_names()
    {
        if (!isset($this->integration_names)) {
            $where = [];
            $mongo = new MongoDBObjects('broker_integrations', $where);
            $integrations = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1, 'name' => 1]]);
            $result = [];
            foreach ($integrations as $integration) {
                $result[MongoDBObjects::get_id($integration)] = GeneralHelper::broker_integration_name($integration); //$integration['name'] ?? '';
            }
            $this->integration_names = $result;
        }
        return $this->integration_names;
    }
}
