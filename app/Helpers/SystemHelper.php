<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Session;

class SystemHelper
{
    public static function systemId()
    {
        $key = 'SystemId';
        $actualSystemId = Session::get($key);
        return $actualSystemId ?? 'crm';
    }
}
