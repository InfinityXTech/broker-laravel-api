<?php

namespace App\Models\TechSupport;

use App\Models\User;
use App\Models\BaseModel;
use App\Models\TechSupport;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

// use Illuminate\Support\Facades\Route;

class TechSupportComment extends BaseModel
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'integration_comments';

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
        "comment",
        "created_by",
        "ticket_id",
        "timestamp"
    ];

    protected $appends = ['current_user_id'];

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by', '_id')->select(['name', 'account_email']);
    }

    public function ticket_number()
    {
        return $this->belongsTo(TechSupport::class, 'ticket_id', '_id')->select(['ticket_number']);
    }

    protected function currentUserId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => Auth::id()
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
