<?php

namespace App\Helpers;

use Closure;

class QueryHelper
{
    public static function buildConditions($conditions)
    {
        $array = array();
        foreach ($conditions as $name => $condition) {
            if (is_array($condition) && count($condition) > 0) {
                $vr = array();

                foreach ($condition as $rpm) {
                    if ($name == 'country') {
                        $vr[] = strtoupper($rpm);
                    } else {
                        // if (is_string($rpm)) { // because of TrafficEndpoint, old records in upper
                        //     $vr[] = strtolower($rpm);                    
                        //     $vr[] = strtoupper($rpm);
                        // } else {
                        $vr[] = $rpm;
                        // }
                    }
                }

                $array[] = array('parameter_name' => $name, 'multiCondition' => true, 'values' => $vr);
            } else if (!is_array($condition)) {
                $array[] = array('parameter_name' => $name, 'multiCondition' => false, 'value' => $condition);
            }
        }

        return $array;
    }

    public static function attachFormula($data, $formulas, array $ext_replace = [])
    {
        if (empty($formulas)) {
            return $data;
        }
        $array = array();
        foreach ($data as $d) {
            foreach ($formulas as $name => $segment) {
                $find = array();
                $find[] = '__leads__';
                $find[] = '__deposits__';
                $find[] = '__approveddeposits__';
                $find[] = '__cost__';
                $find[] = '__revenue__';
                $find[] = '__master_affiliate_payout__';
                $find[] = '__master_brand_payout__';

                $replace = array();
                $replace[] = (int)($d['Leads'] ?? 0);
                $replace[] = (int)($d['Depositors'] ?? 0);
                $replace[] = (int)($d['ApprovedDepositors'] ?? 0);
                $replace[] = (float)round((float)($d['cost'] ?? 0), 2);
                $replace[] = (float)round((float)($d['deposit_revenue'] ?? 0), 2);
                $replace[] = (int)($d['master_affiliate_payout'] ?? 0);
                $replace[] = (int)($d['master_brand_payout'] ?? 0);

                if (!empty($ext_replace)) {
                    foreach ($ext_replace as $k => $r) {
                        $v = '';
                        if (is_string($r)) {
                            $v = $d[$r] ?? '';
                        } else if (is_callable($r)) {
                            $v = $r($k, $d);
                        }
                        $find[] = $k;
                        $replace[] = $v;
                    }
                }

                $rpx = str_replace($find, $replace, $segment);
                $f =  eval('return ' . $rpx . ';');
                $d[$name] = (float)$f;
            }

            $array[] = $d;
        }

        return $array;
    }

    public static function attachMarketingFormula($data, $formulas)
    {
        if (empty($formulas)) {
            return $data;
        }
        $array = array();
        foreach ($data as $d) {
            foreach ($formulas as $name => $segment) {
                $find = array();
                $find[] = '__leads__';
                $find[] = '__conversions__';
                $find[] = '__cost__';
                $find[] = '__revenue__';

                $replace = array();
                $replace[] = (int)($d['Leads'] ?? 0);
                $replace[] = (int)($d['Conversions'] ?? 0);
                $replace[] = (float)round((float)($d['cost'] ?? 0), 2);
                $replace[] = (float)round((float)($d['revenue'] ?? 0), 2);

                $rpx = str_replace($find, $replace, $segment);
                $f =  eval('return ' . $rpx . ';');
                $d[$name] = (float)$f;
            }

            $array[] = $d;
        }

        return $array;
    }

    public static function DataSchema($sample, $except = [])
    {
        $array = array();
        foreach ($sample as $name => $data) {
            if (!in_array($name, $except)) {
                $array[] = $name;
            }
        }
        return $array;
    }

    public static function schemaTitles($titles, $sample, $except = [])
    {
        $schema = array();

        foreach ($sample as $name => $value) {
            if (!in_array($name, $except)) {
                if (isset($titles[$name])) {
                    $schema[] = ['name' => $name, 'title' => $titles[$name]];
                } else {
                    $schema[] = ['name' => $name, 'title' => $name];
                }
            }
        }

        return $schema;
    }
}
