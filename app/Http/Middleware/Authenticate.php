<?php

namespace App\Http\Middleware;

use App\Helpers\GeneralHelper;
use Closure;
use Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
// use Tymon\JWTAuth\Contracts\Providers\Auth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticationMiddleware;

class Authenticate //Middleware
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
        try {
            $jwt = JWTAuth::parseToken()->authenticate();
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            $jwt = false;
        }
        if (Auth::check() || $jwt) {
            return $next($request);
        } else {

            $auth_token = '';
            try {
                $session_hash = $request->header('SessionHash');
                $auth_token = Cache::get($session_hash);
            } catch (Exception $ex) {
            }

            return response()->json([
                'success' => false,
                'google_relogin' => !empty($auth_token),
                'error' => 'Unauthorized'
            ], 401);

            // return response('Unauthorized.', 401);
        }
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
