<?php

namespace App\Models;

use App\Scopes\ClientScope;
use App\Helpers\CryptHelper;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Classes\Eloquent\Traits\EncriptionTrait;
use Jenssegers\Mongodb\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, EncriptionTrait;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    public function routeNotificationForMail($notification)
    {
        // Return both name and email address
        return [$this->account_email => $this->name];
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // using seperate scope class
        static::addGlobalScope(new ClientScope);
    }

    protected static function boot()
    {
        parent::boot();

        parent::creating(function ($model) {
            if (empty($model->clientId)) {
                $model->clientId = ClientHelper::clientId();
            }
            return true;
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        'active',
        'name',
        'last_auth_time',
        'account_email',
        'password',
        'password2',
        'qr_secret',
        'roles',
        'permissions',
        'qr_img',
        'skype',
        'status',
        'token',
        'username',
        'profile',
        'test_lead'
    ];

    // public function password() {
    //     return $this->password2;
    // }

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        // 'password2',
        'remember_token',
    ];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // 'profile' => 'array'
    ];

    // public function getAuthPassword()
    // {
    //     if ($this->enabled_rename_meta && $this->columns['password']) {
    //         return $this->{$this->columns['password']};
    //     }
    //     return $this->password;
    // }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        $username = 'account_email';
        // GeneralHelper::PrintR([$this->columns[$username]]);
        if ($this->enabled_rename_meta && $this->columns[$username]) {
            $username = $this->columns[$username];
        }
        return $username;
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        $password = $this->password;
        $field = 'password';
        if ($this->enabled_rename_meta && isset($this->columns[$field])) {
            $password = $this->{$this->columns[$field]};
        }
        // if ($this->enable_crypt_database && !in_array('password', $this->exclude_crypt_fields)) {
        //     $password = CryptHelper::decrypt($password);
        // }
        return $password;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function hasAnyRoles($roles)
    {
        return count(array_intersect((array)($this->roles ?? []), $roles)) > 0;
    }

    public function hasAnyPermissions($permissions)
    {
        foreach ($permissions as $permission) {
            if (preg_match('/^([^\[]+)\[([^\]]+)]$/i', $permission, $matches)) {
                $base_name = $matches[1];
                $ors = explode('|', $matches[2]);
                $el = ($this->permissions[$base_name] ?? false);

                if ($el) {
                    foreach ($ors as $or_permission) {
                        $_name = $or_permission;
                        $_value = true;
                        if (preg_match('/([^=]+)=([^$]+)$/i', $or_permission, $matches)) {
                            $_name = $matches[1];
                            $_value = $matches[2];
                        }
                        if (is_string($_value)) {
                            $_value = str_replace('{user}', $this->account_email, $_value);
                            $_value = str_replace('{id}', $this->_id, $_value);
                        }
                        if (isset($el[$_name])) {
                            // bool
                            if (is_bool($el[$_name]) && $el[$_name] == boolval($_value)) {
                                return true;
                                // int
                            } else if (is_int($el[$_name]) && $el[$_name] == intval($_value)) {
                                return true;
                                // other
                            } else if ($el[$_name] == $_value) {
                                return true;
                            }
                        } else if (strpos($_name, '->') !== false) {
                            $d = explode('->', $_name);
                            $custom = $d[0];
                            $attr = $d[1];
                            if (isset($el[$custom]) && isset($el[$custom][$attr])) {
                                if (is_string($el[$custom][$attr]) && $el[$custom][$attr] == $_value) {
                                    return true;
                                } else
                                    if (is_array($el[$custom][$attr]) && in_array($_value, $el[$custom][$attr])) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    public function get_profile(string $name, array $default = null)
    {
        return (isset($this->profile) ? ($this->profile[$name] ?? $default) : $default);
    }

    public function set_profile(string $name, array $data)
    {
        $profile = $this->profile ?? [];
        $profile[$name] = $data;
        $this->profile = $profile;
        return $this->save();
    }
}
