<?php

namespace App\Models\Brokers;

use App\Models\BaseModel;
use App\Scopes\ClientScope;
use App\Models\Integrations;
use App\Scopes\BrokerIntegrationScope;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BrokerIntegration extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'broker_integrations';

    public static function boot()
    {
        static::addGlobalScope(new BrokerIntegrationScope);
        parent::boot();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        'status',
        'name',
        'apivendor',
        'partnerId',
        'last_call',
        'total_ftd',
        'today_leads',
        'total_leads',
        'today_revenue',
        'total_revenue',
        'p1',
        'p2',
        'p3',
        'p4',
        'p5',
        'p6',
        'p7',
        'p8',
        'p9',
        'p10',
        'countries',
        'languages',
        'redirect_url',
        'regulated',
        'syncJob'
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => '2',
        'redirect_url' => '',
        'p1' => '',
        'p2' => '',
        'p3' => '',
        'p4' => '',
        'p5' => '',
        'p6' => '',
        'p7' => '',
        'p8' => '',
        'p9' => '',
        'last_call' => 0,
        'syncJob' => true,
    ];

    public function integration()
    {
        return $this->belongsTo(Integrations::class, 'apivendor', '_id');//->withoutGlobalScope(new ClientScope)->select(['name']);
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
