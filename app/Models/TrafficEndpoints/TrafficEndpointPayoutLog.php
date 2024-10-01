<?php

namespace App\Models\TrafficEndpoints;

use App\Models\Master;
use App\Models\BaseModel;
use App\Models\TrafficEndpoint;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class TrafficEndpointPayoutLog extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'endpoint_payouts_log';

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
        'TrafficEndpoint',
        'action',
        'payout_pre',
        'payout',
        'cost_type_pre',
        'cost_type',
        'action',
        'description',
        'timestamp',
        'action_by',
    ];

    public function traffic_endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'TrafficEndpoint')->select(['token']);
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
        // 'enabled' => 'boolean',
        // 'payout' => 'integer'
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
