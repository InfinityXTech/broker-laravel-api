<?php

namespace App\Models\Campaigns;

use App\Models\MarketingCampaign;
use App\Models\BaseModel;
use App\Helpers\GeneralHelper;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MarketingCampaignPayout extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_campaign_payouts';

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
        'campaign',
        'country_code',
        // 'language_code',
        'advertiser_payout',
        'affiliate_payout',
        'enabled',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'enabled' => true,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['country_name'];

    public function campaign_data()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign')->select(['_id', 'name', 'token']);
    }

    protected function countryName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => GeneralHelper::countries()[$attributes['country_code']] ?? $attributes['country_code']
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
        'enabled' => 'boolean'
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
