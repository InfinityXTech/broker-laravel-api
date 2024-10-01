<?php

namespace App\Models\Brokers;

use App\Models\Broker;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Models\Billings\BillingPaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class BrokerBillingChargeback extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_billing_chargebacks';

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
        "broker",
        "payment_method",
        "payment_request",
        "amount",
        "screenshots",

        'proof_screenshots',
        'proof_description',

        'final_approve_files',
        'final_status',
        'final_status_changed_date',
        'final_status_changed_user_id',
        'final_status_changed_user_ip',
        'final_status_changed_user_ua',
        'final_status_date_pay',

        'transaction_id'

    ];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['partner_name', 'token', 'token', 'created_by', 'account_manager']);
    }

    public function payment_method_old_data()
    {
        return $this->belongsTo(BillingPaymentMethod::class, 'payment_method')->select(['payment_method', 'currency_code', 'currency_crypto_code', 'bank_name']);
    }

    public function payment_method_data()
    {
        // return $this->belongsTo(BillingPaymentMethod::class, 'payment_method')->select(['payment_method', 'currency_code', 'currency_crypto_code', 'bank_name']);
        return $this->belongsTo(BrokerBillingPaymentMethod::class, 'payment_method');//->select(['payment_method', 'currency_code', 'currency_crypto_code', 'bank_name']);
    }

    public function payment_request_data()
    {
        return $this->belongsTo(BrokerBillingPaymentRequest::class, 'payment_request')->select(['total']);
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
