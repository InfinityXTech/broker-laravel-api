<?php

namespace App\Models;

use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
// use Illuminate\Support\Facades\Route;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MLeadsEvent extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'mleads_event';

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
        'EventTimeStamp', // CLICK / IMPRESSION / POSTBACK / POSTINSTALL
        'AdvertiserId', //AdvertiserId;
        'CampaignId',
        'AffiliateId',
        'Date',
        'Hour',
        'Minute',
        'EventType', //$event_type; // CLICK / IMPRESSION / POSTBACK / POSTINSTALL / FRAUD
        'clientId', //$CLIENTID; //CLIENT ID
        'AccountId', //$CLIENTID; //CLIENT ID
        'CampaignToken', //$TOKEN; // CAMPAIGN TOKEN
        'CampaignType', //$TYPE;
        'AdvertiserPayout', //$APAYOUT; // Advertiser payout
        'AffiliatePayout', //$AFFILIATE_PAYOUT; // AFFILIATE PAYOUT
        'AdvertiserToken', //$AIToken; // Advertiser Token
        'AffiliateToken', //$AToken; // Advertiser Token
        'SubAffiliateId', //$SubAffiliateId; // Sub Affiliate Id
        'ClickID', //ClickID;
        'AffiliateClickID', //$AffiliateClickID; // AffiliateClickID
        'environment', //$enviorment; // enviorment
        'platform', //$platform; // platform
        'CampaignAppbundle', //$app_bundle; // app bundle
        'CampaignTrackingLink', //$redirectUrl; // redirect url
        'CreativeId', //$CreativeID;
        'CreativeWidth', //$CreativeWidth;
        'CreativeHeight', //$CreativeHeight;
        'ImpressionToken', //$ImpressionToken;
        'AppBundle', //$AppBundle;
        'Site', //$Site;
        'Domain', //Domain;
        'Currency', //Currency;
        'Carrier', //Carrier;
        'Dynamic1', //Dynamic1;
        'Dynamic2', //Dynamic2;
        'Dynamic3', //Dynamic3;
        'Dynamic4', //Dynamic4;
        'Dynamic5', //Dynamic5;
        'Dynamic6', //Dynamic6;
        'HTTP_X_FORWARDED_FOR', //HTTP_X_FORWARDED_FOR;
        'REMOTE_ADDR', //REMOTE_ADDR;
        'execution_time', //execution_time;
        'general_log', //general_log;
        'error_log', //error_log;
        'DocReferrer', //$DocReferrer;
        'UserAgent', //UserAgent;

        'GeoCountryName', //GeoCountryName;
        'GeoRegionName', //GeoRegionName;
        'GeoCityName', //GeoCityName;
        'GeoLatitude', //GeoLatitude;
        'GeoLongitude', //GeoLongitude;

        'DeviceBrand',
        'DeviceType',
        'DeviceOs',
        'OSVersion',
        'Browser',
        'OSBrowser',
        'IsBot',
        'UserLanguage',

        'HTTPS', //HTTPS;
        'iscpc', //true/false;
        'cpc', //PRICE_CPC

        'un_payable',
        
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    // protected $appends = ['status_name'];

    public function advertiser_data()
    {
        return $this->belongsTo(MarketingAdvertiser::class, 'AdvertiserId')->select(['name', 'token']);
    }

    public function affiliate_data()
    {
        return $this->belongsTo(MarketingAffiliate::class, 'AffiliateId')->select(['name', 'token']);
    }

    public function campaign_data()
    {
        return $this->belongsTo(MarketingCampaign::class, 'CampaignId')->select(['name', 'token']);
    }

    // protected function statusName(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn ($value, $attributes) => self::status_names()[$attributes['status'] ?? ''] ?? null,
    //     );
    // }

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
