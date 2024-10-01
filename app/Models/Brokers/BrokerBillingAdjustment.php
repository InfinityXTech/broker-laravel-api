<?php

namespace App\Models\Brokers;

use App\Models\Broker;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class BrokerBillingAdjustment extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_billing_adjustments';

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
        "broker",
        "payment_request",
        "amount",
        "description"
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['amount_sign', 'amount_value'];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['partner_name', 'token', 'created_by', 'account_manager']);
    }

    protected function amountSign(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['amount'] < 0 ? -1 : 1
        );
    }

    protected function amountValue(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => abs($attributes['amount'])
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
    protected $casts = [];
}
