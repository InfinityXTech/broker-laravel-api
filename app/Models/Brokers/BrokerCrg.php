<?php

namespace App\Models\Brokers;

use App\Models\Broker;
use App\Models\BaseModel;
use App\Models\Integrations;
// use Illuminate\Support\Facades\Route;

use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BrokerCrg extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_crg';

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
        "apply_crg_per_endpoint",
        "blocked_schedule",
        "broker",
        "calc_period_crg",
        "country_code",
        "description",
        "end_date",
        "endpoint",
        "ignor_endpoints",
        "ignore_endpoints",
        "ignore_lead_statuses",
        "language_code",
        "leads",
        "limited_leads",
        "min_crg",
        "name",
        "payout",
        "schedule",
        "status",
        "type"
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['name', 'partner_name', 'token', 'created_by', 'account_manager']);
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
