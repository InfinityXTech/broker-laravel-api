<?php

namespace App\Providers;

use App\Classes\Access;
use App\Helpers\GeneralHelper;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    private function add_custom_gate($permission_name, $access_value, $name = '')
    {
        if (is_array($access_value) && count($access_value) > 0 && !array_key_exists('only', $access_value) && !array_key_exists('except', $access_value)) {
            foreach ($access_value as $key => $item) {
                $_name = ($name ?? '') . (empty($name) ? '' : '.') . $key;
                $this->add_custom_gate($permission_name, $item, $_name);
            }
        } else {
            $gate_name = 'custom:' . trim($permission_name) . '[' . trim($name) . ']';
            Gate::define($gate_name, function () use ($access_value) { //before
                return ((is_bool($access_value) && $access_value == true) || boolval($access_value) == true);
            });
        }
    }

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Allow anonymous check for whole policy
        // Gate::acceptsAnonymousCheck('App\Policies\PicturePolicy');

        $user = Auth::user();
        if ($user) {

            // add role (example: role:admin)
            foreach (($user->roles ?? []) as $role) {
                Gate::define('role:' . $role, function ($user) use ($role) { //before
                    return $user->hasAnyRoles([$role]); //count(array_intersect((array)($user->roles ?? []), $roles)) > 0;
                });
            }

            // add permission by name (example: broker: ...[])
            Access::attach_custom_access($user);

            foreach (($user->client['private_features'] ?? []) as $private_feature) {
                $name = 'private_features[' . $private_feature . ']';
                Gate::define($name, function () {
                    return true;
                });
            }

            foreach (($user->client['public_features'] ?? []) as $private_feature) {
                $name = 'public_features[' . $private_feature . ']';
                Gate::define($name, function () {
                    return true;
                });
            }

            foreach (($user->permissions ?? []) as $permission_name => $permission_value) {
                foreach ($permission_value as $access_key => $access_value) {
                    switch ($permission_name) {
                        case '*': {
                                if (!is_array($access_value)) {
                                    // example: custom[deposit_disapproved=true]'
                                    $name = 'custom[' . $access_key . '=' . $access_value . ']';
                                    Gate::define($name, function ($user) use ($access_value, $name) { //before
                                        return (boolval($access_value) == true);
                                    });
                                    // example: custom[deposit_disapproved]
                                    $name = 'custom[' . $access_key . ']';
                                    Gate::define($name, function ($user) use ($access_value, $name) { //before
                                        return (boolval($access_value) == true);
                                    });
                                }
                                break;
                            }
                            // case 'custom': {
                            //         // example: crm_depositors[custom->only={user}]'
                            //         foreach ($access_value as $pn => $pv) {
                            //             if (!is_array($pv)) {
                            //                 $name = $permission_name . '[' . $access_key . '->' . $pn . '=' . $pv . ']';
                            //                 Gate::define($name, function ($user) use ($name) { //before
                            //                     return $user->hasAnyPermissions([$name]);
                            //                 });
                            //             }
                            //         }
                            //         break;
                            //     }
                        default: {
                                if (!is_array($access_value)) {
                                    // example: crm_depositors[only_traffic_endpoint=id]'
                                    $name = $permission_name . '[' . $access_key . '=' . $access_value . ']';
                                    Gate::define($name, function ($user) use ($name) { //before
                                        return $user->hasAnyPermissions([$name]); //count(array_intersect((array)($user->roles ?? []), $roles)) > 0;
                                    });
                                    // example: quality_report[disable_test_lead]'
                                    $name = $permission_name . '[' . $access_key . ']';
                                    Gate::define($name, function ($user) use ($access_value) { //before
                                        return ((is_bool($access_value) && $access_value == true) || boolval($access_value) == true);
                                    });
                                }
                                if ($access_key == 'custom') {
                                    // example: custom:crm[first_name]'
                                    // example: custom:crm[pivot.crg_leads]'
                                    $this->add_custom_gate($permission_name, $access_value);
                                }
                                break;
                            }
                    }
                }
            }
        }
    }
}
