<?php

namespace App\Models\Affiliates;

use App\Models\BaseModel;
use App\Models\MarketingAffiliate;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class AffiliateBillingPaymentMethods extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_affiliate_billing_payment_methods';

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
        "account_name",
        "account_number",
        "bank_name",
        "currency_code",
        "currency_crypto_code",
        "affiliate",
        "notes",
        "payment_method",
        "status",
        "swift",
        "wallet"
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
