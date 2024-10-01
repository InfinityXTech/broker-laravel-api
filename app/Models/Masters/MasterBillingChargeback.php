<?php

namespace App\Models\Masters;

use App\Models\Master;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Models\Masters\MasterBillingPaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MasterBillingChargeback extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'masters_billing_chargebacks';

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
        "master",
        "payment_method",
        "payment_request",
        "amount",
        "screenshots",
    ];

    public function master_data()
    {
        return $this->belongsTo(Master::class, 'master')->select(['partner_name']);
    }

    public function payment_method_data()
    {
        return $this->belongsTo(MasterBillingPaymentMethod::class, 'payment_method')->select(['payment_method', 'currency_code', 'currency_crypto_code', 'bank_name']);
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
