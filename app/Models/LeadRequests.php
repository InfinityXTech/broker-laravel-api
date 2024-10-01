<?php

namespace App\Models;

use App\Models\User;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
// use Illuminate\Support\Facades\Route;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadRequests extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'lead_requests';

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
        "campaign_id",
        "country",
        "email",
        "first_name",
        "funnel_lp",
        "ip",
        "language",
        "last_name",
        "lead_id",
        "phone",
        "timestamp",
        "token",
        "traffic_endpoint_id",
    ];

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
    ];
}
