<?php

namespace App\Models\Affiliates;

use App\Models\BaseModel;
use App\Models\MarketingAffiliate;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class AffiliateBillingChargebacks extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_affiliate_billing_chargebacks';

    protected static function boot()
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
        "amount",
        "affiliate",
        "payment_method",
        "payment_request",
        "screenshots"
    ];

    public function affiliate_data()
    {
        return $this->belongsTo(MarketingAffiliate::class, 'affiliate', '_id')->select(['token']);
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
