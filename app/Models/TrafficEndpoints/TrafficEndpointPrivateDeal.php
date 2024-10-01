<?php

namespace App\Models\TrafficEndpoints;

use App\Models\BaseModel;
use App\Models\TrafficEndpoint;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class TrafficEndpointPrivateDeal extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'endpoint_crg';

    const deal_types = [
        '1' => 'Payout Deal CPA',
        '2' => 'CRG Deal',
        '3' => 'Payout + CRG Deal',
        '4' => 'Payout Deal CPL'
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
        "TrafficEndpoint",
        "blocked_schedule",
        "calc_period_crg",
        "description",
        "end_date",
        "endpoint",
        "ignore_lead_statuses",
        "country_code",
        "language_code",
        "leads",
        "limited_leads",
        "min_crg",
        'max_crg_invalid',
        "name",
        "payout",
        "status",
        "type",
        'funnel_list',
        'sub_publisher_list'
    ];

    protected $appends = ['deal_type_str'];

    public function traffic_endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'TrafficEndpoint')->select(['token']);
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => (int)$value,
            set: fn ($value) => (string)(int)$value,
        );
    }

    protected function dealTypeStr(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => self::deal_types[$this->type] ?? '',
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
        'payout' => 'integer',
    ];
}
