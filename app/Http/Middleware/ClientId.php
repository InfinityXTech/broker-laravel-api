<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\ClientHelper;

class ClientId //Middleware
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
        $clientId = ClientHelper::clientId();
        if (empty($clientId)) {
            return response()->json(['success' => false, 'error' => 'Access Denied'], 422);
        }
        return $next($request);
    }
}
