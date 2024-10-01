<?php

namespace App\Models;

use App\Helpers\GeneralHelper;
use App\Models\User;
use App\Models\BaseModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Models\Brokers\BrokerIntegration;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class Broker extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'partner';

    public static function boot()
    {
        parent::boot();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        "partner_name",
        "token",
        "partner_company",
        "status",
        "account_manager",
        "account_password",
        "account_username",
        "action_on_negative_balance",
        "api_key",
        "balance",
        "balance_timestamp",
        "business_partner_name",
        "city",
        "company_size",
        "company_type",
        "country",
        "financial_status",
        "is_assigned_master_partner",
        "languages",
        "master_partner",
        "net_terms",
        "partner_emailb",
        "partner_emailf",
        "partner_emailt",
        "partner_type",
        "restricted_traffic",
        "restricted_endpoints",
        "skype",
        "street",
        "today_ftd",
        "today_leads",
        "today_revenue",
        "total_ftd",
        "total_leads",
        "total_revenue",
        "wechat",
        "zipcode",
        "broker_crg_already_paid",
        "forbidden_show_traffic_endpoint",
        "billing_manual_status",
        "created_by",
        "tags"
    ];

    // protected $with = [
    //     'broker_data:created_by,token,partner_name'
    // ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 1,
        'partner_type' => '1',
        'action_on_negative_balance' => 'leave_running',
        'master_partner' => null
    ];

    protected $appends = [
        'tag_management'
    ];

    public function toArray()
    {
        $attributes = $this->attributesToArray();

        $attributes['partner_name_secure'] = GeneralHelper::broker_name($attributes);

        // $currentUserId = Auth::id();

        // if (Gate::has('custom[broker_name]') && Gate::denies('custom[broker_name]')) {
        //     $attributes['partner_name_secure'] = $attributes['token'];
        // } else
        // if ($currentUserId == ($attributes['created_by'] ?? '') || $currentUserId == ($attributes['account_manager'] ?? '')) {
        //     $attributes['partner_name_secure'] = $attributes['partner_name'] . (!empty($attributes['token'] ?? '') ? ' (' . $attributes['token'] . ')' : '');
        // } else {
        //     $attributes['partner_name_secure'] = $attributes['token'] ?? 'Unknown';
        // }

        return array_merge($attributes, $this->relationsToArray());
    }

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['name', 'account_email']);
    }

    public function account_manager_user()
    {
        return $this->belongsTo(User::class, 'account_manager', '_id')->select(['name', 'account_email']);
    }

    public function getTagManagementAttribute()
    {
        return TagManagement::whereIn('_id', $this->tags ?? [])->get();
    }

    public function integration_data()
    {
        return $this->hasMany(BrokerIntegration::class, 'partnerId', '_id')->with('integration');
    }

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
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
