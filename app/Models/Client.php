<?php

namespace App\Models;

// use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\BucketHelper;
use Illuminate\Notifications\Notifiable;
use App\Classes\Eloquent\Traits\ArrayTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model // BaseModel
{
    use HasFactory, Notifiable, ArrayTrait;

    protected $connection = 'mongodb';
    protected $collection = 'clients';

    public function array_rows()
    {
        return BucketHelper::get_clients();
    }

    // public static function boot()
    // {
    //     parent::boot();
    // }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "_id",
        "api_domain",
        "crm_domain",
        "nickname",

        "login_background",
        "logo_url_big",
        "logo_url_small",
        "favicon_url",

        "login_background_file",
        "logo_big_file",
        "logo_small_file",
        "favicon_file",

        "partner_domain",
        "partner_api_documentation",
        "partner_login_background",
        "partner_logo_url_big",
        "partner_logo_url_small",
        "partner_favicon_url",

        "partner_login_background_file",
        "partner_logo_big_file",
        "partner_logo_small_file",
        "partner_favicon_file",

        "master_domain",
        "master_login_background",
        "master_logo_url_big",
        "master_logo_url_small",
        "master_favicon_url",

        "master_login_background_file",
        "master_logo_big_file",
        "master_logo_small_file",
        "master_favicon_file",

        "marketing_affiliates_api_domain",
        "marketing_affiliates_domain",
        "marketing_tracking_domain",

        "redirect_domain",
        "serving_domain",

        "created_by",
        "created_at",
        "status",

        // new
        "client_type",
        "plan_type",
        "account_manager",
        "package",
        "custom_amount",
        "revshare_amount",
        "ui_domain",
        // "api_domain",

        "db_connection_type",
        "db_connection_id",
        "db_crypt_key",
        "db_crypt_iv",

        "integrations",

        // features
        "private_features",
        "public_features"
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        "_id" => 'string',
        "api_domain" => 'string',
        "crm_domain" => 'string',
        "ui_domain" => 'string',
        "nickname" => 'string',

        "client_type" => 'string',
        "plan_type" => 'string',

        'status' => 'string',
        'db_connection' => 'array',
        'private_connection' => 'array',
        'integrations' => 'array',
        'private_features' => 'array',
        'public_features' => 'array',
        'package' => 'array',
    ];
}
