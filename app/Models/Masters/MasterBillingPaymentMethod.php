<?php

namespace App\Models\Masters;

use App\Models\Master;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MasterBillingPaymentMethod extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'masters_billing_payment_methods';

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
        "master",
        "account_name",
        "account_number",
        "bank_name",
        "currency_code",
        "currency_crypto_code",
        "currency_crypto_wallet_type",
        "notes",
        "payment_method",
        "status",
        "swift",
        "wallet",
        "wallet2",
        "files",
    ];

    public function master_data()
    {
        return $this->belongsTo(Master::class, 'master')->select(['partner_name']);
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
