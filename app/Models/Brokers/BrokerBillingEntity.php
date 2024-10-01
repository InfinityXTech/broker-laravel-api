<?php

namespace App\Models\Brokers;

use App\Models\Broker;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class BrokerBillingEntity extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_billing_entities';

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
        'broker',
        'company_legal_name',
        'country_code',
        'region',
        'city',
        'zip_code',
        'currency_code',
        'vat_id',
        'registration_number',
        'files',
    ];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['partner_name', 'token', 'created_by', 'account_manager']);
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
