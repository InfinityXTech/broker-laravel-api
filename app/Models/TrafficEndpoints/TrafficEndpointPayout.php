<?php

namespace App\Models\TrafficEndpoints;

use App\Models\BaseModel;
use App\Models\TrafficEndpoint;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class TrafficEndpointPayout extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'endpoint_payouts';

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
        "TrafficEndpoint",
        "cost_type",
        "country_code",
        "distributions_crg",
        "weekend_off_distributions_crg",
        "enabled",
        "language_code",
        "payout",
        "daily_cap"
    ];

    public function traffic_endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'TrafficEndpoint')->select(['token']);
    }

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'enabled' => true,
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
        'enabled' => 'boolean'
    ];
}
