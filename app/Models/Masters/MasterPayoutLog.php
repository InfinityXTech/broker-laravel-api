<?php

namespace App\Models\Masters;

use App\Models\Master;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MasterPayoutLog extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'Master_payouts';

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
        'master',
        'action',
        'payout_pre',
        'payout',
        'cost_type_pre',
        'cost_type',
        'action',
        'description',
        'timestamp',
        'action_by',
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
    protected $casts = [
        // 'enabled' => 'boolean',
        // 'payout' => 'integer'
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
