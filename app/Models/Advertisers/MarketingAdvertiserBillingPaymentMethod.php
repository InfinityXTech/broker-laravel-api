<?php

namespace App\Models\Advertisers;

use App\Models\MarketingAdvertiser;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MarketingAdvertiserBillingPaymentMethod extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_advertiser_billing_payment_methods';

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
        "advertiser",
        "payment_method",
        "status",
    ];

    public function advertiser_data()
    {
        return $this->belongsTo(MarketingAdvertiser::class, 'advertiser')->select(['name']);
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
