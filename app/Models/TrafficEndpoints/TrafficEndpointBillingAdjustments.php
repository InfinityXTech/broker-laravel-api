<?php

namespace App\Models\TrafficEndpoints;

use App\Models\BaseModel;
use App\Models\TrafficEndpoint;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class TrafficEndpointBillingAdjustments extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'endpoint_billing_adjustments';

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
        "amount",
        "description",
        "endpoint",
        "bi",
        "bi_timestamp",
        "payment_request",
    ];

    public function traffic_endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'endpoint')->select(['token']);
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
    protected $casts = [];
}
