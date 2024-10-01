<?php

namespace App\Models\Affiliates;

use App\Models\BaseModel;
use App\Models\MarketingAffiliate;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class AffiliateBillingEntities extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_affiliate_billing_entities';

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
        "city",
        "company_legal_name",
        "country_code",
        "currency_code",
        "affiliate",
        "region",
        "zip_code",
        'vat_id',
        'registration_number',
        'files',
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
