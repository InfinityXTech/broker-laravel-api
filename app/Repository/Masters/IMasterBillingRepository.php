<?php

namespace App\Repository\Masters;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IMasterBillingRepository extends IRepository
{

    public function get_payment_methods(string $masterId): array;
    public function get_payment_requests_for_chargeback(string $masterId): array;

    public function get_change_logs(string $masterId, bool $extended, string $collection, int $limit): array;

    public function get_general_balance(string $masterId): array;

    public function feed_entities(string $masterId): Collection;
    public function get_entity(string $modelId): ?Model;
    public function create_entity(array $payload): ?Model;
    public function update_entity(string $modelId, array $payload): bool;
    public function delete_entity(string $modelId): bool;

    public function feed_chargebacks(string $masterId): Collection;
    public function get_chargeback(string $modelId): ?Model;
    public function create_chargeback(array $payload): ?Model;
    public function update_chargeback(string $modelId, array $payload): bool;
    public function delete_chargeback(string $modelId): bool;

    public function feed_adjustments(string $masterId): Collection;
    public function get_adjustment(string $modelId): ?Model;
    public function create_adjustment(array $payload): ?Model;
    public function update_adjustment(string $modelId, array $payload): bool;
    public function delete_adjustment(string $modelId): bool;

    public function feed_payment_methods(string $masterId): array;
    public function select_payment_method(string $masterId, string $methodId): bool;
    public function create_billing_payment_methods(string $masterId, array $payload): string;
    public function update_billing_payment_methods(string $masterId, string $payment_method_id, array $payload): bool;
    public function files_billing_payment_methods(string $masterId, string $payment_method_id): array;

    public function feed_payment_requests(string $masterId, bool $only_completed): Collection;
    public function feed_payment_requests_query(string $masterId, array $payload): array;
    public function create_payment_request(string $masterId, array $payload): array;
    public function get_payment_request(string $modelId): ?Model;
    public function get_payment_request_calculations(string $masterId, string $modelId): array;
    public function get_payment_request_invoice(string $masterId, string $modelId): void;
    public function get_payment_request_files(string $modelId): array;
    public function payment_request_approve(string $masterId, string $modelId, array $payload): bool;
    public function payment_request_master_approve(string $masterId, string $modelId, array $payload): bool;
    public function payment_request_reject(string $masterId, string $modelId): bool;
    public function payment_request_fin_approve(string $masterId, string $modelId, array $payload): bool;
    public function payment_request_fin_reject(string $masterId, string $modelId): bool;
    public function payment_request_real_income(string $masterId, string $modelId, array $payload): bool;
    public function payment_request_archive(string $masterId, string $modelId): bool;
}
