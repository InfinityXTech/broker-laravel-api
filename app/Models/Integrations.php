<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Helpers\BucketHelper;
use App\Scopes\IntegrationScope;
// use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Classes\Eloquent\Traits\ArrayTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class Integrations extends Model //BaseModel
{
    use HasFactory, Notifiable, ArrayTrait;
    // use ArrayTrait;

    protected $connection = 'mongodb';
    protected $collection = 'Integrations';

    public function array_rows() {
        return BucketHelper::get_integrations();
    }

    // public static function boot()
    // {
    //     // static::addGlobalScope(new IntegrationScope);
    //     parent::boot();
    // }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "_id",
        "name",
        "p1",
        "p2",
        "p3",
        "p4",
        "p5",
        "p6",
        "p7",
        "p8",
        "p9",
        "p10",
        "status"
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
        "name" => 'string',
        "p1" => 'string',
        "p2" => 'string',
        "p3" => 'string',
        "p4" => 'string',
        "p5" => 'string',
        "p6" => 'string',
        "p7" => 'string',
        "p8" => 'string',
        "p9" => 'string',
        "p10" => 'string',
        "status" => 'string',
        // 'created_at' => 'datetime',
        // 'updated_at' => 'datetime',
        // 'active' => 'bool',
    ];
}
