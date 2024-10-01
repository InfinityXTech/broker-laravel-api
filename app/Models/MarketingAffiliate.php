<?php

namespace App\Models;

use App\Models\User;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarketingAffiliate extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'marketing_affiliates';

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
        "name",
        "email_md5",
        "email_encrypted",
        "description",
        "token",
        "status",
        "qr_secret",
        "api_key",
        "account_password",
        "login_qr",
        "created_at",
        "created_by",
        "account_manager",
        "postback",
        "event_postback",
        "blocked_offers_type",
        "blocked_offers",
        "manual_approve",

        "billing_manual_status",

        "under_review",
        // from register
        "ip",
        "email_confirmed",
        "full_name",
        "skype",
        "telegram",
        "whatsapp",

        "aff_dashboard_pass"
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 1,
    ];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['name', 'account_email']);
    }

    public function account_manager_user()
    {
        return $this->belongsTo(User::class, 'account_manager', '_id')->select(['name', 'account_email']);
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
