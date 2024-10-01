<?php

namespace App\Models\Brokers;

use App\Models\Broker;
use App\Models\BaseModel;
use App\Models\Integrations;
// use Illuminate\Support\Facades\Route;

use App\Helpers\GeneralHelper;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BrokerCaps extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_caps';

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
        "blocked_funnels",
        "blocked_funnels_type",
        "blocked_schedule",
        "broker",
        "cap_type",
        "country_code",
        "daily_cap",
        "priority",
        "endpoint_priorities",
        "enable_traffic",
        "endpoint_dailycaps",
        "endpoint_livecaps",
        "integration",
        "language_code",
        "live_caps",
        "note",
        "period_type",
        "restrict_endpoints",
        "restrict_type"
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [];

    public function integration_data()
    {
        return $this->belongsTo(BrokerIntegration::class, 'integration', '_id')->select(['name', 'status']);
    }

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker', '_id')->select(['partner_name', 'token', 'status', 'financial_status', 'created_by', 'account_manager']);
    }

    protected function brokerName(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $broker = Broker::query()->find($attributes['broker']);
                if ($broker) {
                    return GeneralHelper::broker_name($broker);
                }
                return $attributes['broker'];
            }
        );
    }

    protected function restrictType(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => empty($attributes['restrict_endpoints'] ?? []) ? null : $value
        );
    }

    protected function blockedFunnelsType(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => empty($attributes['blocked_funnels'] ?? []) ? null : $value
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
        'daily_cap' => 'integer',
        'live_caps' => 'integer',
    ];
}
