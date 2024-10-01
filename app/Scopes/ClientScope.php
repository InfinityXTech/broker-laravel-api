<?php

namespace App\Scopes;

use App\Models\Client;
use App\Models\Integrations;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class ClientScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (!$model instanceof Client && !$model instanceof Integrations) {
            $clientId = ClientHelper::clientId();
            $builder->where('clientId', $clientId);
        }
    }

    public function remove(Builder $builder)
    {

        $query = $builder->getQuery();

        // here you remove the where close to allow developer load
        // without your global scope condition

        foreach ((array)$query->wheres as $key => $where) {

            if ($where['column'] == 'clientId') {

                unset($query->wheres[$key]);

                $query->wheres = array_values($query->wheres);
            }
        }
    }
}
