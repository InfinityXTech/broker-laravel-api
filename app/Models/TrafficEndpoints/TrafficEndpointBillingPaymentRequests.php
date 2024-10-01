<?php

namespace App\Models\TrafficEndpoints;

use App\Models\User;
use App\Models\BaseModel;
use App\Models\TrafficEndpoint;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class TrafficEndpointBillingPaymentRequests extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'endpoint_billing_payment_requests';

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
        "adjustment_amount",
        "adjustment_description",
        "chargeback",
        "cost",
        "created_by",
        "endpoint",
        "final_approve_files",
        "final_status",
        "final_status_changed_date",
        "final_status_changed_user_id",
        "final_status_changed_user_ip",
        "final_status_changed_user_ua",
        "final_status_date_pay",
        "from",
        "json_leads",
        "leads",
        "master_approve_invoice_files",
        "master_approve_files",
        "master_approve_note",
        "master_status",
        "master_status_changed_date",
        "master_status_changed_user_id",
        "master_status_changed_user_ip",
        "master_status_changed_user_ua",
        "payment_method",
        "status",
        "sub_status",
        "status_changed_date",
        "status_changed_user_id",
        "status_changed_user_ip",
        "status_changed_user_ua",
        "timestamp",
        "to",
        "total",
        "type",
        'transaction_id',
        'hash_url',
        'proof_screenshots',
        'proof_description',

    ];

    public function traffic_endpoint_data()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'endpoint', '_id')->select(['token']);
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
    protected $casts = [];
}
