<?php

namespace App\Repository\Settings;

use App\Helpers\ClientHelper;
use App\Helpers\StorageHelper;
use App\Models\PlatformAccounts;

use App\Repository\BaseRepository;
use App\Models\BillingPaymentMethods;
use App\Models\BillingPaymentCompanies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Settings\ISettingsRepository;

class SettingsRepository extends BaseRepository implements ISettingsRepository
{

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct()
    {
    }

    public function get_settings_model(): ?Model
    {
        $model = PlatformAccounts::query()->first();
        if (!$model) {
            $model = new PlatformAccounts();
            $insert = [
                'clientId' => ClientHelper::clientId(),
                'cdn_url' => '',
                'marketing_suite_domain_url' => '',
                'marketing_suite_tracking_url' => '',
                'subscribers' => []
            ];
            $model->fill($insert);
            $model->save();
        }
        return $model;
    }

    public function get(): ?Model
    {
        return $this->get_settings_model();
    }

    public function set(array $payload): bool
    {
        $model = $this->get_settings_model();
        return $model->update($payload);
    }

    public function feed_payment_methods(): Collection
    {
        return BillingPaymentMethods::all();
    }

    public function create_payment_methods(array $payload): bool
    {
        $model = new BillingPaymentMethods();

        StorageHelper::syncFiles('billing_payment_methods', null, $payload, 'files', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);

        $model->fill($payload);

        return $model->save();
    }

    public function feed_payment_companies(): Collection
    {
        return BillingPaymentCompanies::all();
    }

    public function create_payment_companies(array $payload): bool
    {
        $model = new BillingPaymentCompanies();
        $model->fill($payload);

        return $model->save();
    }

    public function get_subscribers(): array
    {
        $model = $this->get_settings_model();
        return $model->subscribers ?? [];
    }

    public function update_subscribers(array $payload): bool
    {
        $model = $this->get_settings_model();
        $model->fill(['subscribers' => $payload]);
        return $model->save();
    }
}
