<?php

namespace App\Models\TrafficEndpoints;

use App\Models\BaseModel;
use App\Models\TrafficEndpoint;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class TrafficEndpointSubPublisherToken extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'endpoint_sub_publisher_tokens';

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
        "traffic_endpoint",
        "token",
        "sub_publisher",
    ];

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
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
