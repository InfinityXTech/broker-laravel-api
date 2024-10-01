<?php

namespace App\Classes\Mongo;

use App\Helpers\GeneralHelper;
use Exception;

class MongoQueryEval
{
    private static $cache = [];

    private static function convert($v, $t)
    {
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
                    return ($v ? TRUE : FALSE);
                }
            case 'string': {
                    return $v;
                }
            case 'date': {
                    return date("d-m-Y", $v);
                }
            case 'datetime': {
                    return date("d-m-Y H:i:s", $v);
                }
        }
        return $v;
    }

    public static function get_value($result, $f, $t)
    {
        if (!isset($result[$f])) {
            return NULL;
        }
        return self::convert($result[$f], $t);
    }

    public static function Exec($formula, $result, $t)
    {
        $func = self::$cache[$formula] ?? null;

        if (!$func) {
            $func = preg_replace_callback(
                '|__(.*?)__|',
                function ($matches) {
                    $f = $matches[1];
                    $t = '$_t';

                    preg_match('/\(([^\)]+)\)([^$]+)/', $f, $matches);
                    if ($matches && count($matches) > 1) {
                        $t = "'" . $matches[1] . "'";
                        $f = $matches[2];
                    }

                    return 'self::get_value($_result, \'' . $f . '\', ' . $t . ')';
                },
                $formula
            );
            $func = 'return function($_result, $_t) {' . $func . '};';
            try {
                $func = eval($func);
            } catch(\ParseError $ex) {
                // GeneralHelper::PrintR(['ParseError: ' . $func, 'err' => $ex]);
                // die();
                throw $ex;
            }catch (\Throwable $ex) {
                // GeneralHelper::PrintR(['Throwable: ' . $func]);
                // die();
                throw $ex;
            } catch (Exception $ex) {
                // GeneralHelper::PrintR(['func: ' . $func]);
                // die();
                // throw $ex;
            }
            self::$cache[$formula] = $func;
        }

        return $func($result, $t);
    }
}
