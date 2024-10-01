<?php

namespace App\Models;

use App\Models\Broker;
use App\Models\BaseModel;
use App\Models\LeadsReviewSupport;
use Jenssegers\Mongodb\Eloquent\Model;
// use Illuminate\Support\Facades\Route;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Leads extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'leads';

    public static function boot()
    {
        parent::boot();
    }

    public static function status_names()
    {
        return [
            "callback" => ['title' => 'Callback', 'regex' => "Call([\s]+)?back"],
            "no_answer" => ['title' => 'No Answer', 'regex' => "No Answer"],
            "callBack_personal" => ['title' => 'CallBack Personal', 'regex' => "Call back([\s]+)?-([\s]+)?Personal"],
            "wrong_number" => ['title' => 'Wrong Number', 'regex' => "Wrong Number"],
            "not_interested" => ['title' => 'Not Interested', 'regex' => "Not Interested"],
            "do_not_call" => ['title' => 'Do Not Call', 'regex' => "Do Not Call"],
            "language_barrier" => ['title' => 'Language Barrier', 'regex' => "Language barrier"],
            "payment_decline" => ['title' => 'Payment Decline', 'regex' => "Payment Decline"],
            "low_quality" => ['title' => 'Low Quality', 'regex' => "Low Quality"],
            "test" => ['title' => 'Test', 'regex' => "test"],
            "under_age" => ['title' => 'Under Age', 'regex' => "Under Age"],
            "calling" => ['title' => 'Calling', 'regex' => "Calling"],
            "new" => ['title' => 'New', 'regex' => "New"],

            // "1" => "New",
            // "2" => "Call([\s]+)?back",
            // "3" => "Call([\s]+)?back([\s]+)?-([\s]+)?Personal",
            // "4" => "No Answer",
            // "5" => "Wrong Number",
            // "6" => "Depositor",
            // "7" => "Declined Deposit"
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "Browser",
        "CampaignId",
        "DOI",
        "DeviceBrand",
        "DeviceType",
        "MasterAffiliate",
        "master_brand",
        "Master_brand_cost",
        "Mastercost",
        "OS",
        "OSBrowser",
        "OSVersion",
        "Time",
        "Timestamp",
        "TrafficEndpoint",
        "UserLanguage",
        "fakeDepositor",
        "endpointDepositTimestamp",
        "age",
        "brokerCapId",
        "brokerId",
        "broker_cpl",
        "broker_crg_deal",
        "broker_crg_payout",
        "broker_crg_payout_id",
        "broker_crg_percentage",
        "broker_crg_percentage_id",
        "broker_crg_percentage_period",
        'broker_changed_crg_percentage_id',
        'broker_changed_crg_percentage_period',
        'broker_changed_crg_percentage',
        'broker_changed_crg_payout_id',
        'broker_changed_crg_payout',
        "broker_lead_id",
        "broker_status",
        "city",
        "connection_type",
        "cost",
        "country",
        "country_prefix",
        "creative_id",
        "crg_deal",
        "crg_ftd_uncount",
        "crg_master_payout",
        "crg_payout",
        "crg_percentage",
        "crg_percentage_id",
        "crg_percentage_period",
        'changed_crg_percentage_id',
        'changed_crg_percentage_period',
        'changed_crg_percentage',
        'changed_crg_payout_id',
        'changed_crg_payout',
        "d1",
        "d10",
        "d2",
        "d3",
        "d4",
        "d5",
        "d6",
        "d7",
        "d8",
        "d9",
        "day",
        "dayofweek",
        "depositTimestamp",
        "depositTypeGravity",
        "deposit_by_pixel",
        "deposit_disapproved",
        "deposit_reject",
        "deposit_revenue",
        "depositor",
        "email",
        "first_name",
        "funnel_lp",
        "gender",
        "hour",
        "integrationId",
        "ip",
        "isCPL",
        "isMasterCPL",
        "isMasterBrandCPL",
        "isp",
        "language",
        "last_name",
        "latitude",
        "log_get",
        "log_post",
        "log_server",
        "longitude",
        "marketing_suite_click_id",
        "master_affiliate",
        "master_affiliate_calculation",
        "master_affiliate_payout",
        "master_brand_calculation",
        "match_with_broker",
        "media_account_id",
        "minute",
        "month",
        "password",
        "phone",
        "publisher_click",
        "realDepositTimestamp",
        "real_country",
        "real_language",
        "received_by_marketing_suite",
        "redirect_url",
        "region",
        "region_code",
        "revenue",
        "scrubSource",
        "short_phone",
        "status",
        "sub_publisher",
        "syncedDepositRevenue",
        "test_lead",
        "useragent",
        "waiting_for_manual_approved",
        "zip_code",
        "review_status",
        "broker_cpl_unpaid",
        "cpl_unpaid"
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['status_name'];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'brokerId')->select(['partner_name', 'token', 'created_by', 'account_manager']);
    }

    public function endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'TrafficEndpoint')->select(['token']);
    }

    protected function statusName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::status_names()[$attributes['status'] ?? ''] ?? null,
        );
    }

    public function leads_review_tickets_count()
    {
        return $this->hasMany(LeadsReviewSupport::class, 'leadId', '_id');
        // return $this->belongsToMany(Leads::class, LeadsReviewSupport::class, '_id', 'leadId')->all(['_id', 'ticketNumber']);
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

    public function alerts () {
        return $this->hasMany(Alert::class, 'lead_id');
    }
}
