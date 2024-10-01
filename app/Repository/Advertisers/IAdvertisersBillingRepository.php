<?php

namespace App\Repository\Advertisers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IAdvertisersBillingRepository extends IRepository
{
    public function get_general_balance(string $advertiserId): array;
    public function get_general_balance_logs(string $advertiserId, int $page, int $count_in_page = 20): array;

    public function get_general_crg_logs(string $advertiserId, array $payload): array;

    public function update_general_balance_logs(string $advertiserId, string $logId, array $payload): bool;
    public function get_change_logs(string $advertiserId, bool $extended, string $collection, int $limit): array;
    public function set_negative_balance_action(string $advertiserId, string $action): bool;
    public function set_credit_amount(string $advertiserId, int $amount): bool;
    public function set_manual_status(string $advertiserId, string $manual_status): bool;

    public function feed_entities(string $advertiserId): Collection;
    public function get_entity(string $modelId): ?Model;
    public function create_entity(array $payload): ?Model;
    public function update_entity(string $modelId, array $payload): bool;
    public function delete_entity(string $modelId): bool;

    public function feed_chargebacks(string $advertiserId): Collection;
    public function get_chargeback(string $modelId): ?Model;
    public function create_chargeback(array $payload): ?Model;
    public function update_chargeback(string $modelId, array $payload): bool;
    public function delete_chargeback(string $modelId): bool;

    public function feed_adjustments(string $advertiserId): Collection;
    public function get_adjustment(string $modelId): ?Model;
    public function create_adjustment(array $payload): ?Model;
    public function update_adjustment(string $modelId, array $payload): bool;
    public function delete_adjustment(string $modelId): bool;

    public function feed_payment_methods(string $advertiserId): Collection;
    public function select_payment_method(string $advertiserId, string $methodId): bool;

    public function feed_payment_requests(string $advertiserId, bool $only_completed): Collection;
    public function feed_payment_requests_query(string $advertiserId, array $payload): array;
    public function create_payment_request(string $advertiserId, array $payload): string;
    public function get_payment_request(string $modelId): ?Model;
    public function get_payment_request_calculations(string $advertiserId, string $modelId): array;
    public function get_payment_request_invoice(string $advertiserId, string $modelId): void;
    public function get_payment_request_files(string $modelId): array;
    public function payment_request_approve(string $advertiserId, string $modelId, array $payload): bool;
    public function payment_request_change(string $advertiserId, string $modelId, array $payload): bool;
    public function payment_request_reject(string $advertiserId, string $modelId): bool;
    public function payment_request_fin_approve(string $advertiserId, string $modelId, array $payload): bool;
    public function payment_request_fin_reject(string $advertiserId, string $modelId): bool;
    public function payment_request_real_income(string $advertiserId, string $modelId, array $payload): bool;
    public function payment_request_archive(string $advertiserId, string $modelId): bool;
}
