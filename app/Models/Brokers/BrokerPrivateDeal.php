<?php

namespace App\Models\Brokers;

use App\Helpers\GeneralHelper;
use App\Models\Broker;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class BrokerPrivateDeal extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_crg';


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
        'broker',
        'name',
        'type',
        'status',
        'description',
        'endpoint',
        'ignore_endpoints',
        'only_integrations',
        'limited_leads',
        'leads',
        'end_date',
        'ignore_lead_statuses',
        'apply_crg_per_endpoint',
        'country_code',
        'language_code',
        'min_crg',
        'max_crg_invalid',
        'calc_period_crg',
        'payout',
        'blocked_schedule',
        'funnel_list',
        'sub_publisher_list'
    ];

    protected $appends = ['deal_type_str'];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['partner_name', 'token', 'created_by', 'account_manager']);
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => (int)$value,
            set: fn ($value) => (string)(int)$value,
        );
    }

    // protected function status(): Attribute
    // {
    //     return Attribute::make(
    //         get: function ($value) {
    //             try {
    //                 return (int)$value;
    //             } catch (\Exception $ex) {
    //             };
    //             return '0';
    //         },
    //         set: fn ($value) => (string)(int)$value,
    //     );
    // }

    protected function endDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => GeneralHelper::ToMongoDateTime($value),
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
