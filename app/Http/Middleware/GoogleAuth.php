<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class GoogleAuth //Middleware
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
        $validator = Validator::make($request->input(), [
            '__auth_code__' => 'required|string',
            'session_hash' => 'string|min:2|nullable'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $validator->validated();

        $user = Auth::user();
        $qr_secret = '';

        if ($user == null) {
            $name = $data['session_hash'] ?? false;
            if ($name) {
                $auth_data = Cache::get($name);
                if ($auth_data) {
                    $qr_secret = $auth_data['qr_secret'];
                }
            }
        } else {
            $qr_secret = $user->qr_secret;
        }

        // if ($user == null) {
        //     return response()->json([
        //         '__auth_code__' => ['User is not authorized']
        //     ], 401);
        // }

        if (empty($qr_secret)) {
            return response()->json([
                '__auth_code__' => ['Secret in the session is empty']
            ], 422);
        }

        $ga = new \PHPGangsta_GoogleAuthenticator();
        $oneCode = $ga->getCode($qr_secret);

        if ($oneCode == $data['__auth_code__']) {
            return $next($request);
        }

        return response()->json([
            '__auth_code__' => ['Google code is not valid']
        ], 401);
    }
}
