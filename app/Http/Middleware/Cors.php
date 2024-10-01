<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Origin', config('cors.allowed_origins'));
        $response->headers->set('Access-Control-Allow-Methods', config('cors.allowed_methods'));
        $response->headers->set('Access-Control-Allow-Headers', config('cors.allowed_headers'));
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
