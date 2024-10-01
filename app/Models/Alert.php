<?php

namespace App\Models;

use App\Models\BaseModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Alert extends BaseModel
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'drt2';
    
    protected $fillable = [
        "execution_at",
        "category",
        "lead_id",
        "status",
        "created_by"
    ];
    
    protected $with = [
        'user',
        'lead.endpoint_data',
        'lead.broker_data'
    ];

    protected $appends = ['time'];

    public function lead () {
        return $this->belongsTo(Leads::class, 'lead_id');
    }

    public function user () {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTimeAttribute () {
        $date = Carbon::createFromTimestampMs($this->execution_at['$date']['$numberLong']);
        $date->setTimezone('EAT');
        
        return [
            'total' => $date->toDateTimeString(),
            'left' => $date->diffForHumans(),
        ];
    }
}
