<?php

namespace App\Helpers;

class RenderHelper
{
    public static function format_datetime($data, $format)
    {
        return date($format, ((array)$data)['milliseconds'] / 1000);
    }

    public static function format_money($number)
    {
        $echo_result = number_format($number, 2, ',', ' ');
        $echo_result = str_replace('$-', '-$', '$' . $echo_result);
        return $echo_result;
    }

    public static function format_money_color($number)
    {
        $echo_result = number_format($number, 2, ',', ' ');
        $echo_result = str_replace('$-', '-$', '$' . $echo_result);
        if ($number < 0) {
            return '<div style="display:inline;color:red">' . $echo_result . '</div>';
        }
        if ($number > 0) {
            return '<div style="display:inline;color:green">' . $echo_result . '</div>';
        }
        return $echo_result;
    }
}
