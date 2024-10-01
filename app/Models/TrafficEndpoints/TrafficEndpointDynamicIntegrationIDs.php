<?php

namespace App\Models\TrafficEndpoints;

use App\Models\Broker;
use App\Models\BaseModel;
use App\Models\TrafficEndpoint;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Models\Brokers\BrokerIntegration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class TrafficEndpointDynamicIntegrationIDs extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'endpoint_dynamic_integration_ids';

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
        "status",
        "TrafficEndpoint",
        "brokerId",
        "integrationId",
        "DV1",
        "DV2",
        "DV3",
        "hits_today",
        "hits_lifetime"
    ];

    public function traffic_endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'TrafficEndpoint')->select(['token']);
    }

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'brokerId')->select(['partner_name', 'token', 'created_by', 'account_manager']);
    }

    public function integration_data()
    {
        return $this->belongsTo(BrokerIntegration::class, 'integrationId', '_id')->select(['name', 'status']);
    }

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 1,
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
        // 'status' => 'string'
    ];
}
