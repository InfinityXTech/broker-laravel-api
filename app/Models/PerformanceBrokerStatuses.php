<?php

namespace App\Models;

use App\Models\User;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
// use Illuminate\Support\Facades\Route;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PerformanceBrokerStatuses extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_performance_statuses';

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
        "status",
        "category",
        "priority",
    ];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['name', 'account_email']);
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
