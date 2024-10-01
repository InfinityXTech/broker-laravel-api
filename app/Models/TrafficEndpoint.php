<?php

namespace App\Models;

use App\Models\User;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
// use Illuminate\Support\Facades\Route;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrafficEndpoint extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'TrafficEndpoints';

    public static function boot()
    {
        parent::boot();
    }

    public function cacheKey()
    {
        return sprintf(
            "%s/%s-%s",
            $this->getTable(),
            $this->getKey(),
            $this->updated_at->timestamp
        );
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        "account_manager",
        "account_password",
        "aff_dashboard_pass",
        "api_key",
        "token",
        "blocked_funnels",
        "company_type",
        "traffic_quality",
        "country",
        "created_by",
        // "dashboard_permissions",
        "endpoint_status",
        "endpoint_type",
        "is_assigned_master_partner",
        "lead_postback",
        "login_qr",
        "marketing_suite_offers",
        "marketing_suite_traffic_endpoinds",
        "master_partner",
        "permissions",
        "postback",
        "qr_secret",
        "redirect_24_7",
        "replace_funnel_list",
        "restricted_brokers",
        "restricted_brokers_by_country",
        "traffic_sources",
        "status",
        "statusDeposit",
        "statusMatching",
        "statusReporting",
        "billing_manual_status",
        "send_mismatch_again",

        "today_leads",
        "total_leads",
        "today_deposits",
        "total_deposits",
        "today_revenue",
        "total_revenue",

        "UnderReview",
        "ApplicationJson",

        "probation",
        "in_house",
        "tags",
        "deactivation_reason",
        "deactivation_reason_duplicated",
        "deactivation_reason_other"
    ];

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
        // 'replace_funnel_list' => 'array',
        // 'traffic_sources'
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];

    protected $appends = [
        'tag_management'
    ];

}
