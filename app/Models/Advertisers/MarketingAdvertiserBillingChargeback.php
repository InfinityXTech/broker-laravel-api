<?php

namespace App\Models\Advertisers;

use App\Models\MarketingAdvertiser;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Models\Billings\BillingPaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MarketingAdvertiserBillingChargeback extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_advertiser_billing_chargebacks';

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->amount = (float)$model->amount;
        });
        static::updating(function ($model) {
            $model->amount = (float)$model->amount;
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        "advertiser",
        "payment_method",
        "payment_request",
        "amount",
        "screenshots",
    ];

    public function advertiser_data()
    {
        return $this->belongsTo(MarketingAdvertiser::class, 'advertiser')->select(['name']);
    }

    public function payment_method_data()
    {
        return $this->belongsTo(BillingPaymentMethod::class, 'payment_method')->select(['payment_method', 'currency_code', 'currency_crypto_code', 'bank_name']);
    }

    public function payment_request_data()
    {
        return $this->belongsTo(MarketingAdvertiserBillingPaymentRequest::class, 'payment_request')->select(['total']);
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
        'amount' => 'integer'
    ];
}
