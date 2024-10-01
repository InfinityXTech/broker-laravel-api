<?php

namespace App\Models\Brokers;

use App\Models\Broker;
use App\Models\BaseModel;
use App\Helpers\GeneralHelper;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class BrokerPayout extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_payouts';

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
        'country_code',
        'language_code',
        'cost_type',
        'payout',
        'enabled',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'enabled' => true,
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['country_name', 'language_name'];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['partner_name', 'token', 'created_by', 'account_manager']);
    }

    protected function countryName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => GeneralHelper::countries()[$attributes['country_code'] ?? ''] ?? $attributes['country_code']
        );
    }

    protected function languageName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => GeneralHelper::languages()[$attributes['language_code'] ?? ''] ?? 'general'// $attributes['language_code']
        );
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
        'enabled' => 'boolean'
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
