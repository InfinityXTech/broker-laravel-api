<?php

namespace App\Scopes;

use App\Models\Client;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Models\Brokers\BrokerIntegration;
use Illuminate\Database\Eloquent\Builder;

class BrokerIntegrationScope implements Scope
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
        if ($model instanceof BrokerIntegration) {
            // $clientConfig = ClientHelper::get_bucket_client();
            // $integrations_ids = $clientConfig["integrations"] ?? ['nothing'];
            // $builder->whereIn('apivendor', $integrations_ids);
        }
    }
}
