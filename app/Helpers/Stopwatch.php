<?php

namespace App\Helpers;

class Stopwatch
{
    private static $timers = [];

    public static function start($timer = 'default')
    {
        self::$timers[$timer] = microtime(true);
    }

    public static function stop($timer = 'default')
    {
        $result = microtime(true) - self::$timers[$timer];
        unset(self::$timers[$timer]);
        header('X-Stopwatch: ' . $timer . ' ' . $result);
        return $result;
    }
}