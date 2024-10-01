<?php

namespace App\Models\Brokers;

use App\Helpers\GeneralHelper;
use App\Models\Broker;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
// use Illuminate\Support\Facades\Route;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BrokerStatus extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_statuses';

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
        'status',
        'broker_status',
    ];

    protected $attributes = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['status_name'];

    public function broker_data()
    {
        return $this->belongsTo(Broker::class, 'broker')->select(['partner_name', 'token', 'created_by', 'account_manager']);
    }

    protected function statusName(): Attribute
    {
        return Attribute::make(
            // get: fn ($value, $attributes) => '122',
            get: fn ($value, $attributes) => self::status_names()[$attributes['status'] ?? '11'] ?? null,
            // get: function ($value, $attributes) {
            //     GeneralHelper::PrintR($attributes);die();
            //     // return self::status_names()[$attributes['status'] ?? ''] ?? null
            //     return '';
            // },
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
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];

    public static function status_names()
    {
        return [
            'callback' => 'Callback',
            'no_answer' => 'No Answer',
            'callback_personal' => 'Call back - Personal',
            'wrong_number' => 'Wrong Number',
            'not_interested' => 'Not Interested',
            'do_not_call' => 'Do Not Call',
            'language_barrier' => 'Language barrier',
            'payment_decline' => 'Payment Decline',
            'low_quality' => 'Low Quality',
            'test' => 'test',
            'under_age' => 'Under Age',
            'calling' => 'Calling',
            'new' => 'New',
            'invalid' => 'Invalid'
        ];
    }
}
