<?php

namespace App\Models;

use App\Helpers\CryptHelper;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Models\TechSupport\TechSupportComment;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// use Illuminate\Support\Facades\Route;

class LeadsReviewSupport extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'leads_review_support';

    const status = [
        '1' => 'Open',
        '2' => 'Rejected',
        '3' => 'In Progress',
        '4' => 'Completed'
    ];

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
        "leadId",
        "createdBy",
        "files",
        "note",
        "status",
        "ticketNumber",
        "timestamp",
    ];

    protected $appends = ['status_name'];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'createdBy', '_id')->select(['name', 'account_email']);
    }

    public function lead_data()
    {
        return $this->belongsTo(Leads::class, 'leadId')
            ->select(['_id', 'brokerId', 'TrafficEndpoint', 'country', 'language', 'email', 'broker_lead_id', 'redirect_url'])
            ->with(['broker_data', 'endpoint_data'])
            ->map(function ($item) {
                CryptHelper::decrypt_lead_data_model($item);
                return $item;
            });
    }

    protected function statusName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::status[(string)$this->status] ?? ''
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
