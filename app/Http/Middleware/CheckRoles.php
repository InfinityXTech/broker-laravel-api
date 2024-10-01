<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CheckRoles
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {

        $url = $request->url();

        $user = $request->user(); //Auth::user();

        $roles = is_array($roles) ? $roles : explode(',', $roles);

        //add all permission 
        if (
            $user &&
            count($roles) > 0 &&
            strpos($url, '/auth/google') === false &&
            strpos($url, '/auth/login') === false &&
            strpos($url, '/auth/profile') === false
        ) {

            // Admin has all permissions:
            Gate::before(function ($user, $ability) {
                if (in_array('admin', (array)($user->roles ?? []))) {
                    return true;
                }
            });

            if (in_array('admin', (array)$user->roles)) {
                return $next($request);
            }

            // if ($user->can('access', $roles)) {
            //     return $next($request);
            // }

            if ($user->hasAnyRoles($roles)) {
                return $next($request);
            }

            foreach ($roles as $role) {
                Gate::define($role, function ($user) use ($roles) { //before
                    return $user->hasAnyRoles($roles); //count(array_intersect((array)($user->roles ?? []), $roles)) > 0;
                });
            }

            return response()->json([
                'success' => false,
                'error' => 'Access Denied'
            ], 401);
        }

        return $next($request);
    }
}
