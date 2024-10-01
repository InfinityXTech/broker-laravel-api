<?php

namespace App\Models;

use App\Scopes\ClientScope;
use App\Helpers\CryptHelper;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Classes\History\HistoryDB;
use Jenssegers\Mongodb\Eloquent\Model;
use App\Classes\History\HistoryDBAction;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use App\Classes\Eloquent\Traits\EncriptionTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Support\Facades\Route;

class BaseModel extends Model
{
    use HasFactory, Notifiable, EncriptionTrait;

    protected $connection = 'mongodb';
    protected $collection = '';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // using seperate scope class
        static::addGlobalScope(new ClientScope);
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        // static::addGlobalScope('approve', function (Builder $builder) {
        //     // $builder->where('clientId', ClientHelper::clientId());
        // });

        $default = config('database.default');

        $connections = config('database-log.connections');
        $log_enable = $connections[$default]['enable'] ?? false;

        parent::creating(function ($model) {
            if (empty($model->clientId)) {
                $model->clientId = ClientHelper::clientId();
            }
            return true;
        });

        parent::created(function ($model) use ($log_enable) {
            if ($log_enable) {

                $log_params = self::get_log_params($model->collection);

                if ($log_params !== false) {

                    $modelName = get_called_class();
                    $key = app($modelName)->getKeyName();
                    $id = $model->{$key};

                    if (!empty($id)) {
                        $changes = $model->toArray();
                        if (count($changes) > 0) {
                            if (isset($log_params['main_foreign_field']) && !empty($log_params['main_foreign_field'])) {
                                if (!isset($changes[$log_params['main_foreign_field']])) {
                                    $changes[$log_params['main_foreign_field']] = $model[$log_params['main_foreign_field']] ?? '';
                                }
                            }
                            self::add_log(HistoryDBAction::Insert, $model->collection, $id, $changes, $log_params);
                        }
                    }
                }
            }
        });

        parent::updating(function ($model) use ($log_enable) {
            if ($log_enable) {

                $log_params = self::get_log_params($model->collection);

                if ($log_params !== false) {

                    $modelName = get_called_class();
                    $key = app($modelName)->getKeyName();
                    $id = $model->{$key};

                    if (!empty($id)) {

                        $dirty = $model->getDirty();
                        $changes = [];
                        foreach ($dirty as $field => $newdata) {
                            $olddata = $model[$field];
                            // echo $olddata . '!=' . $newdata . PHP_EOL;
                            if ($olddata != $newdata) {
                                $changes[$field] = $newdata;
                            }
                        }

                        // почему-то при разных методах сохранения не всегда есть изменения. Временно, позже пересмотреть
                        if (count($changes) == 0) {
                            $changes = $dirty;
                        }

                        if (count($changes) > 0) {
                            if (isset($log_params['main_foreign_field']) && !empty($log_params['main_foreign_field'])) {
                                if (!isset($changes[$log_params['main_foreign_field']])) {
                                    $changes[$log_params['main_foreign_field']] = $model[$log_params['main_foreign_field']] ?? '';
                                }
                            }
                            self::add_log(HistoryDBAction::Update, $model->collection, $id, $changes, $log_params);
                        }
                    }
                }

                // $changes = $model->getChanges();
                // $original = $model->getOriginal();
                // $dirty = $model->getDirty();
                // if (!$model->isDirty('created_by')) {
                //     $model->created_by = auth()->user()->id;
                // }
                // if (!$model->isDirty('updated_by')) {
                //     $model->updated_by = auth()->user()->id;
                // }
            }
        });

        parent::updated(function ($model) {
        });

        parent::deleting(function ($model)  use ($log_enable) {
            if ($log_enable) {

                $modelName = get_called_class();
                $key = app($modelName)->getKeyName();
                $id = $model->{$key};

                if (!empty($id)) {
                    $changes = $model->toArray();
                    if (count($changes) > 0) {
                        $log_params = self::get_log_params($model->collection);
                        if ($log_params) {
                            if (isset($log_params['main_foreign_field']) && !empty($log_params['main_foreign_field'])) {
                                if (!isset($changes[$log_params['main_foreign_field']])) {
                                    $changes[$log_params['main_foreign_field']] = $model[$log_params['main_foreign_field']] ?? '';
                                }
                            }
                            self::add_log(HistoryDBAction::Delete, $model->collection, $id, $changes, $log_params);
                        }
                    }
                }
            }
        });

        parent::deleted(function ($model) {
        });

        // parent::saving(function ($model) use ($log_enable) {
        //     if ($log_enable) {
        //         $modelName = get_called_class();
        //         $key = app($modelName)->getKeyName();
        //         $id = $model->{$key};
        //         if (!empty($id)) {
        //             $original = self::findOrFail($id);
        //             $dirty = $model->getDirty();
        //             $changes = [];
        //             foreach ($dirty as $field => $newdata) {
        //                 $olddata = $original[$field];
        //                 if ($olddata != $newdata) {
        //                     $changes[$field] = $newdata;
        //                 }
        //             }
        //             if (count($changes) > 0) {
        //                 $log_params = self::get_log_params($model->collection);
        //                 if ($log_params) {
        //                     if (isset($log_params['main_foreign_field']) && !empty($log_params['main_foreign_field'])) {
        //                         if (!isset($changes[$log_params['main_foreign_field']])) {
        //                             $changes[$log_params['main_foreign_field']] = $original[$log_params['main_foreign_field']] ?? '';
        //                         }
        //                     }
        //                     self::add_log(HistoryDBAction::Update, $model->collection, $id, $changes, $log_params);
        //                 }
        //             }
        //         }
        //     }
        // });

        parent::saved(function ($model) {
        });
    }

    private static function get_log_params(string $collection)
    {
        $default = config('database.default');
        $connections = config('database-log.connections');
        $log_collections = $connections[$default]['collections'] ?? [];
        $collections = collect(config('crypt-schemas'))->map(fn($item, $key) => $item['collection'])->flip();
        $collection = $collections[$collection] ?? $collection;

        $params = false;
        if (isset($log_collections[$collection])) {
            $params = $log_collections[$collection];
            // GeneralHelper::Dump(['dd' => '123']);die();
        }
        return $params;
    }

    private static function add_log(string $action, string $collection, string $id, array $data, array $log_params)
    {
        if (isset($log_params)) {
            $collections = collect(config('crypt-schemas'))->map(fn($item, $key) => $item['collection'])->flip();
            $collection = $collections[$collection] ?? $collection;    
            $main_foreign_field = isset($log_params['main_foreign_field']) ? $log_params['main_foreign_field'] : '';
            $fields = isset($log_params['fields']) ? $log_params['fields'] : [];
            //GeneralHelper::Dump($action, $collection, $data, $id, $main_foreign_field, $fields);
            HistoryDB::add($action, $collection, $data, $id, $main_foreign_field, $fields);
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];

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
