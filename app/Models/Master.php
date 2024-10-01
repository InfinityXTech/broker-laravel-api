<?php

namespace App\Models;

use stdClass;
use App\Models\User;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
// use Illuminate\Support\Facades\Route;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Master extends BaseModel
{
    use HasFactory, Notifiable;

    public static function boot()
    {
        parent::boot();
    }

    public static $types = ['1' => 'Master Affiliate', '2' => 'Master Brand'];

    public static $type_of_calculations = [
        'fixed_price' => ['title' => 'Fixed price', 'default_value' => 200, 'symbol' => '$'],
        'percentage' => ['title' => 'Percentage from Revenue', 'default_value' => 15, 'symbol' => '%'],
        'percentage_profit' => ['title' => 'Percentage from Profit', 'default_value' => 15, 'symbol' => '%']
    ];

    protected $connection = 'mongodb';
    protected $collection = 'Masters';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        "assignedto",
        "calculation_price",
        "country",
        "created_by",
        "fixed_price_cpl",
        "location",
        "master_status",
        "password",
        "qr_code",
        "qr_secret",
        "status",
        "timestamp",
        "today_cost",
        "today_ftd",
        "today_leads",
        "today_revenue",
        "token",
        "total_cost",
        "total_ftd",
        "total_leads",
        "type",
        "type_of_calculation",
        "nickname"
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['type_name', 'type_of_calculation_data'];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['name', 'account_email']);
    }

    public function assignedto_user()
    {
        return $this->belongsTo(User::class, 'assignedto', '_id')->select(['name', 'account_email']);
    }

    protected function typeName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::$types[(string)$this->type] ?? ''
        );
    }

    protected function typeOfCalculationData(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::$type_of_calculations[(string)$this->type_of_calculation] ?? []
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
}
