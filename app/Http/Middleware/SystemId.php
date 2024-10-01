<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Helpers\GeneralHelper;
use Tymon\JWTAuth\Facades\JWTAuth;
// use Tymon\JWTAuth\Contracts\Providers\Auth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class SystemId //Middleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $key = 'SystemId';
        try {
            $systemId = $request->header($key);
            $actualSystemId = Session::get($key);
            if ($actualSystemId != $systemId) {
                Session::put($key, $systemId);
            }
        } catch (Exception $ex) {
        }

        return $next($request);
    }

    // /**
    //  * Get the path the user should be redirected to when they are not authenticated.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return string|null
    //  */
    // protected function redirectTo($request)
    // {
    //     if (!$request->expectsJson()) {
    //         return route('login');
    //     }
    //     // return request()->json([
    //     //     'success' => false,
    //     //     'error' => 'You are not Authenticated'
    //     // ]);

    // }

}
