<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$permissions)
    {

        $url = $request->url();

        $user = $request->user(); //Auth::user();

        $permissions = is_array($permissions) ? $permissions : explode(',', $permissions);

        //add all permission 
        if (
            $user &&
            count($permissions) > 0 &&
            strpos($url, '/auth/google') === false &&
            strpos($url, '/auth/login') === false &&
            strpos($url, '/auth/profile') === false
        ) {

            // Admin has all permissions:
            // Gate::before(function ($user, $ability) {
            //     if (in_array('admin', (array)($user->roles ?? []))) {
            //         return true;
            //     }
            // });

            // if (in_array('admin', (array)$user->roles)) {
            //     return $next($request);
            // }

            // ----- if ($user->can('access', $permissions)) {
            //     return $next($request);
            // }

            foreach ($permissions as $role) {
                Gate::define($role, function ($user) use ($permissions) { //before
                    return $user->hasAnyPermissions($permissions); //count(array_intersect((array)($user->roles ?? []), $roles)) > 0;
                });
            }

            if ($user->hasAnyPermissions($permissions)) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'error' => 'Access Denied'
            ], 403);
        }

        return $next($request);
    }
}
