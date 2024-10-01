<?php

namespace App\Repository\Brokers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IBrokerBillingRepository extends IRepository
{
    public function get_general_balance(string $brokerId): array;
    public function get_general_balance_logs(string $brokerId, int $page, int $count_in_page = 20): array;

    public function get_general_recalculate_logs(string $brokerId, array $payload): array;

    public function update_general_balance_logs(string $brokerId, string $logId, array $payload): bool;
    public function get_change_logs(string $brokerId, bool $extended, string $collection, int $limit): array;
    public function set_negative_balance_action(string $brokerId, string $action): bool;
    public function set_credit_amount(string $brokerId, int $amount): bool;
    public function set_manual_status(string $brokerId, string $manual_status): bool;

    public function feed_entities(string $brokerId): Collection;
    public function get_entity(string $modelId): ?Model;
    public function create_entity(array $payload): ?Model;
    public function update_entity(string $modelId, array $payload): bool;
    public function delete_entity(string $modelId): bool;

    public function feed_chargebacks(string $brokerId): array;
    public function get_chargeback(string $modelId): ?Model;
    public function create_chargeback(array $payload): ?Model;
    public function update_chargeback(string $modelId, array $payload): bool;
    public function delete_chargeback(string $modelId): bool;
    public function fin_approve_chargeback(string $brokerId, string $modelId, array $payload): bool;
    public function fin_reject_chargeback(string $brokerId, string $modelId): bool;
    public function files_chargebacks(string $brokerId, string $id): array;

    public function feed_adjustments(string $brokerId): Collection;
    public function get_adjustment(string $modelId): ?Model;
    public function create_adjustment(array $payload): ?Model;
    public function update_adjustment(string $modelId, array $payload): bool;
    public function delete_adjustment(string $modelId): bool;

    // our
    public function feed_our_payment_methods(string $brokerId): Collection;
    public function select_our_payment_method(string $brokerId, string $methodId): bool;

    // broker
    public function create_payment_method(string $brokerId, array $payload): ?Model;
    public function update_payment_methods(string $paymentMethodId, array $payload): bool;
    public function feed_payment_methods(string $brokerId): array;
    public function select_payment_method(string $brokerId, string $methodId): bool;
    public function files_payment_methods(string $brokerId, string $paymentMethodId): array;

    public function feed_payment_requests(string $brokerId, bool $only_completed): Collection;
    public function feed_payment_requests_query(string $brokerId, array $payload): array;
    public function create_payment_request(string $brokerId, array $payload): string;
    public function get_payment_request(string $modelId): ?Model;
    public function get_payment_request_calculations(string $brokerId, string $modelId): array;
    public function get_payment_request_invoice(string $brokerId, string $modelId): void;
    public function get_payment_request_files(string $modelId): array;
    public function payment_request_approve(string $brokerId, string $modelId, array $payload): bool;
    public function payment_request_change(string $brokerId, string $modelId, array $payload): bool;
    public function payment_request_reject(string $brokerId, string $modelId): bool;
    public function payment_request_fin_approve(string $brokerId, string $modelId, array $payload): bool;
    public function payment_request_fin_reject(string $brokerId, string $modelId): bool;
    public function payment_request_real_income(string $brokerId, string $modelId, array $payload): bool;
    public function payment_request_archive(string $brokerId, string $modelId): bool;
}
