<?php

namespace App\Helpers;

use Dotenv\Repository\Adapter\ImmutableWriter;
use Dotenv\Repository\AdapterRepository;
use Illuminate\Support\Env;

class DynamicEnvironment
{
    public static function set(string $key, string $value)
    {
        $closure_adapter = \Closure::bind(function &(AdapterRepository $class) {
            $closure_writer = \Closure::bind(function &(ImmutableWriter $class) {
                return $class->writer;
            }, null, ImmutableWriter::class);
            return $closure_writer($class->writer);
        }, null, AdapterRepository::class);
        return $closure_adapter(Env::getRepository())->write($key, $value);
    }
}
