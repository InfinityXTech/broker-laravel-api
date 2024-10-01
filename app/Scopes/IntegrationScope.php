<?php

namespace App\Scopes;

use App\Models\Client;
use App\Models\Integrations;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class IntegrationScope implements Scope
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
        if ($model instanceof Integrations) {
            $clientConfig = ClientHelper::get_bucket_client();
            $integrations_ids = array_map(fn ($id) => new \MongoDB\BSON\ObjectId($id), $clientConfig["integrations"] ?? []);
            if (empty($integrations_ids)) {
                $integrations_ids[] = 'nothing';
            }
            $builder->whereIn('_id', $integrations_ids);
        }
    }
}
