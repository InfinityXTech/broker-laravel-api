<?php

namespace App\Models\Masters;

use App\Models\Master;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MasterBillingAdjustment extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'masters_billing_adjustments';

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
        "amount",
        "payment_request",
        "description"
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['amount_sign', 'amount_value'];

    public function master_data()
    {
        return $this->belongsTo(Master::class, 'master')->select(['partner_name']);
    }

    protected function amountSign(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['amount'] < 0 ? -1 : 1
        );
    }

    protected function amountValue(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => abs($attributes['amount'])
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
    protected $casts = [];
}
