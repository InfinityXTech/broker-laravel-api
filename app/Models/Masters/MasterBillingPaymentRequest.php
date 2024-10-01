<?php

namespace App\Models\Masters;

use App\Models\Master;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class MasterBillingPaymentRequest extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'masters_billing_payment_requests';

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
        "cost",
        "created_by",
        "final_status",
        "final_status_changed_date",
        "final_status_date_pay",
        "final_status_changed_user_id",
        "final_status_changed_user_ip",
        "final_status_changed_user_ua",
        "final_approve_files",
        "from",
        "json_leads",
        "leads",
        "master_approve_note",
        "master_status",
        "master_status_changed_date",
        "master_status_changed_user_id",
        "master_status_changed_user_ip",
        "master_status_changed_user_ua",
        "master_approve_files",
        "affiliate_approve_files",
        "affiliate_invoices",
        "status",
        "status_changed_date",
        "status_changed_user_id",
        "status_changed_user_ip",
        "status_changed_user_ua",
        "sub_status",
        "timestamp",
        "payment_fee",
        "to",
        "total",
        "type",
        "chargeback",
        'transaction_id',
        'hash_url',
        'proof_screenshots',
        'proof_description',
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
        'payment_fee' => 'float'
    ];
}
