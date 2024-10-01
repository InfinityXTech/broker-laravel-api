<?php

namespace App\Models;

use App\Models\User;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Campaigns\MarketingCampaignTargetingLocation;

class MarketingCampaign extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_campaigns';

    const categories = [
        'accessories' => 'Accessories',
        'addiction' => 'Addiction',
        'adult' => 'Adult',
        'app' => 'App',
        'beauty' => 'Beauty',
        'betting' => 'Betting',
        'billing' => 'Billing',
        'biz_opp' => 'Biz Opp',
        'cams' => 'Cams',
        'casino' => 'Casino',
        'cbd' => 'CBD',
        'cc_submit' => 'CC Submit',
        'clothing' => 'Clothing',
        'coupons' => 'Coupons',
        'cpa' => 'CPA',
        'cpi' => 'CPI',
        'cpl' => 'CPL',
        'cpm' => 'CPM',
        'credit' => 'Credit',
        'crypto' => 'Crypto',
        'dating' => 'Dating',
        'debt' => 'Debt',
        'desktop' => 'Desktop',
        'diet' => 'Diet',
        'display' => 'Display',
        'downloads' => 'Downloads',
        'ecommerce' => 'eCommerce',
        'education' => 'Education',
        'email' => 'Email',
        'email_submit' => 'Email Submit',
        'entertainment' => 'Entertainment',
        'facebook' => 'Facebook',
        'fashion' => 'Fashion',
        'financial' => 'Financial',
        'forex' => 'Forex',
        'free_trial' => 'Free Trial',
        'gambling' => 'Gambling',
        'gaming' => 'Gaming',
        'health' => 'Health',
        'home_services' => 'Home Services',
        'install' => 'Install',
        'insurance' => 'Insurance',
        'international' => 'International',
        'investment' => 'Investment',
        'lead_gen' => 'Lead Gen',
        'legal' => 'Legal',
        'loan' => 'Loan',
        'mainstream' => 'Mainstream',
        'make_money' => 'Make Money',
        'mobile' => 'Mobile',
        'music' => 'Music',
        'nutra' => 'Nutra',
        'pay_per_call' => 'Pay Per Call',
        'recovery' => 'Recovery',
        'rehab' => 'Rehab',
        'search' => 'Search',
        'seo' => 'SEO',
        'shopping' => 'Shopping',
        'smartlink' => 'Smartlink',
        'social_media' => 'Social Media',
        'software' => 'Software',
        'sports' => 'Sports',
        'streaming' => 'Streaming',
        'survey' => 'Survey',
        'sweepstakes' => 'Sweepstakes',
        'tech' => 'Tech',
        'travel' => 'Travel',
        'vod' => 'VOD',
        'weight_loss' => 'Weight Loss',
    ];

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
        "advertiserId",
        "name",
        "description",
        "category",
        "token",
        "status",
        "country",
        "language",
        "created_at",
        "created_by",
        "account_manager",

        "advertiser_general_payout",
        "affiliate_general_payout",

        "environment",
        "desktop_operating_system",
        "mobile_operating_system",
        "event_type",
        "tracking_link",
        "tracking_preview_link",
        "tracking_country_link",

        "general_cap",
        "daily_cap",

        'force_sub_publisher',
        'check_max_mind',
        'time_start',
        'time_end',
        'blocked_schedule',
        'restrict_endpoints',
        'restrict_type',
        'post_event',

        'world_wide_targeting',

        'screenshot_image',

        'tags'
    ];

    protected $appends = ['category_title'];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 1,
    ];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['name', 'account_email']);
    }

    public function targeting_data()
    {
        // $columns = ['country_code', 'country_name', 'region_codes', 'region_name'];
        // $relations = [];
        return $this->hasMany(MarketingCampaignTargetingLocation::class, 'campaign', '_id');//->select($columns)->with($relations);
    }

    protected function categoryTitle(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::categories[(string)$this->category] ?? ''
        );
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
        'world_wide_targeting' => 'bool'
    ];
}
