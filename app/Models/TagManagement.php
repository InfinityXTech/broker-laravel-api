<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class TagManagement extends BaseModel
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'tag_management';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "clientId",
        'name',
        'status',
        'permission',
        'color',
        'created_by'
    ];

    protected $with = [
        'created_by_user'
    ];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['id', 'name']);
    }
}
