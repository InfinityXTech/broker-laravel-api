<?php

namespace App\Models\Brokers;

use App\Models\Broker;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class BrokerBillingPaymentMethod extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_billing_payment_methods';

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

        "type", // 'our', 'broker'

        // own | broker
        "payment_method",
        "status",

        // broker
        "account_name",
        "account_number",
        "bank_name",
        "currency_code",
        "currency_crypto_code",
        "currency_crypto_wallet_type",
        "notes",
        
        // "payment_method",
        // "status",
        
        "swift",
        "wallet",
        "wallet2",
        "files"
    ];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['partner_name', 'token', 'token', 'created_by', 'account_manager']);
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
