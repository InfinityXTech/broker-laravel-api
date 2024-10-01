<?php

namespace App\Models\Campaigns;

use App\Models\MarketingCampaign;
use App\Models\BaseModel;
use App\Helpers\GeneralHelper;
use App\Models\TrafficEndpoint;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MarketingCampaignLimitationEndpoints extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_campaign_limitation_endpoints';

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
        'traffic_endpoint',
        'parameter',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        // 'enabled' => true,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];

    public function campaign_data()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign')->select(['name', 'token']);
    }

    public function traffic_endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'traffic_endpoint')->select(['token']);
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
        // 'enabled' => 'boolean'
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
