<?php

namespace App\Models\Advertisers;

use App\Models\User;
use App\Models\MarketingAdvertiser;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MarketingAdvertiserBillingPaymentRequest extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_advertiser_billing_payment_requests';

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
        'advertiser',
        'type',
        'from',
        'to',
        'status',
        'leads',
        'cost',
        'adjustment_amount',
        'adjustment_description',
        'total',
        'json_leads',
        'timestamp',
        'billing_entity',
        'billing_from',
        'payment_fee',
        'payment_method',
        'status_changed_date',
        'status_changed_user_id',
        'status_changed_user_ip',
        'status_changed_user_ua',
        'final_approve_files',
        'final_status',
        'final_status_changed_date',
        'final_status_changed_user_id',
        'final_status_changed_user_ip',
        'final_status_changed_user_ua',
        'final_status_date_pay',
        'chargeback',
        'transaction_id'
    ];

    public function advertiser_data()
    {
        return $this->belongsTo(MarketingAdvertiser::class, 'advertiser')->select(['name']);
    }

    public function final_status_changed_user()
    {
        return $this->belongsTo(User::class, 'final_status_changed_user_id', '_id')->select(['name', 'account_email']);
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
        'payment_fee' => 'float'
    ];
}
