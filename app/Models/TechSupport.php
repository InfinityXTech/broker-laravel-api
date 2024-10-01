<?php

namespace App\Models;

use App\Helpers\GeneralHelper;
use App\Models\BaseModel;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Models\TechSupport\TechSupportComment;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\BSON\ObjectId;

// use Illuminate\Support\Facades\Route;

class TechSupport extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'integration';

    public static function boot()
    {
        parent::boot();
    }

    const add_types = [
        '1' => 'Request Integration',
        '2' => 'Report A Bug',
        '3' => 'Request Product Feature',
        '5' => 'Financial Investigation',
        '4' => 'Others'
    ];

    const types = [
        '1' => 'Integration Request',
        '2' => 'Bug Report',
        '3' => 'Feature Request',
        '5' => 'Financial Investigation',
        '4' => 'Others'
    ];

    const integration = [
        '1' => 'Broker',
        '2' => 'Traffic Endpoint',
    ];

    const status = [
        '1' => 'Open',
        '2' => 'Rejected',
        '3' => 'In Progress',
        '4' => 'Completed'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        "broker",
        "broker_api_documentstion",
        "created_by",
        "files",
        "note",
        "status",
        "ticket_number",
        "timestamp",
        "traffic_endpoint",
        "type",
        "integration",
        "taken_to_work",
        "finished",
        "assigned_to",
        "subject"
    ];

    protected $appends = ['type_name', 'status_name', 'assigned_to_users_data'];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['name', 'account_email']);
    }

    protected function AssignedToUsersData(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $ids = [];
                if (is_array($this->assigned_to)) {
                    foreach ($this->assigned_to ?? [] as $assigned_to) {
                        $ids[] = new ObjectId($assigned_to);
                    }
                } else {
                    $ids = [new ObjectId($this->assigned_to)];
                }
                if (count($ids) > 0) {
                    return User::query()->whereIn('_id', $ids)->get(['name', 'account_email'])->toArray();
                }
                return [];
            }
        );
    }

    // public function assigned_to_user_one()
    // {
    //     return $this->belongsTo(User::class, 'assigned_to', '_id')->select(['name', 'account_email']);
    // }

    // public function assigned_to_user()
    // {
    //     $relations = [
    //         "assigned_to_user_one:name,account_email",
    //     ];
    //     return $this->hasMany(User::class, 'assigned_to2', '_id')->select(['name', 'account_email']); //->with($relations);
    // }

    public function broker_user()
    {
        return $this->belongsTo(Broker::class, 'broker', '_id')->select(['partner_name', 'token', 'created_by', 'account_manager']);
    }

    public function comments()
    {
        $relations = [
            "created_by_user:name,account_email",
        ];

        return $this->hasMany(TechSupportComment::class, 'ticket_id', '_id')->select(['*'])->with($relations);
    }

    public function endpoint_user()
    {
        return $this->belongsTo(TrafficEndpoint::class, 'traffic_endpoint', '_id')->select(['token']);
    }

    protected function typeName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::types[(string)$this->type] ?? ''
        );
    }

    protected function statusName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::status[(string)($this->status ?? '')] ?? ''
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
