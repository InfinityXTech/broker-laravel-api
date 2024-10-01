<?php

namespace App\Repository\Dictionaries;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IDictionaryRepository extends IRepository {
    public function index(array $dictionaries): array;
    public function brokers(): array;
    public function traffic_endpoints(): array;
    public function users(): array;
    public function master_brands(): array;
    public function master_affiliates(): array;
    public function countries(): array;
    public function languages(): array;
    public function traffic_sources(): array;
    public function integrations(): array;
    public function broker_statuses(): array;
    public function timezones(): array;

    public function marketing_suite_verticals(): array;
    public function marketing_suite_platforms(): array;
    public function marketing_suite_conversion_types(): array;
    public function marketing_suite_ristrictions(): array;
    public function marketing_suite_categories(): array;

    public function billing_payment_companies(): array;

    public function currency_rates(string $datetime): array;

    public function regions(string $country_code): array;
    public function marketing_post_events(string $advertiserId): array;
    
}