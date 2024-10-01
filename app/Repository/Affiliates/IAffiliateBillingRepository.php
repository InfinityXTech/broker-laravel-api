<?php

namespace App\Repository\Affiliates;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IAffiliateBillingRepository extends IRepository
{

    public function get_payment_methods(string $affiliateId): array;
    public function get_payment_requests_for_chargeback(string $affiliateId): array;

    public function set_manual_status(string $affiliateId, string $manual_status): bool;

    public function feed_billing_general_balances(string $affiliateId): array;
    public function feed_billing_balances_log(string $affiliateId, int $page, int $count_in_page = 20): array;
    public function history_log_billing_general_balances(string $affiliateId, array $payload): array;
    public function update_billing_balances_log(string $affiliateId, string $logId, array $payload): bool;

    public function get_crg_logs(string $affiliateId, array $payload): array;

    public function feed_billing_entities(string $affiliateId): array;
    public function get_billing_entities(string $affiliateId, string $entityId): ?Model;
    public function create_billing_entities(string $affiliateId, array $payload): ?Model;
    public function update_billing_entities(string $affiliateId, string $entityId, array $payload): bool;
    public function remove_billing_entities(string $affiliateId, string $entityId): bool;

    public function feed_billing_payment_methods(string $affiliateId): array;
    public function create_billing_payment_methods(string $affiliateId, array $payload): string;
    public function active_billing_payment_methods(string $affiliateId, string $paymentMethodId);

    public function feed_billing_payment_requests(string $affiliateId): array;
    public function get_billing_payment_requests(string $affiliateId, string $paymentRequestId): array;
    public function get_billing_payment_request_view_calculation(string $affiliateId, string $paymentRequestId): array;
    public function crg_details(string $affiliateId, string $crgId, array $payload): array;
    public function pre_create_billing_payment_requests(string $affiliateId, array $payload): array;
    public function create_billing_payment_requests(string $affiliateId, array $payload): array;
    public function files_billing_payment_requests(string $affiliateId, string $paymentRequestId): array;
    public function approve_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array;
    public function reject_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array;
    public function master_approve_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array;
    public function real_income_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array;
    public function final_approve_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array;
    public function final_reject_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array;
    public function archive_rejected_billing_payment_requests(string $affiliateId, string $paymentRequestId, array $payload): array;
    public function get_payment_request_invoice(string $affiliateId, string $paymentRequestId);

    public function feed_completed_transactions(string $affiliateId): array;

    public function feed_adjustments(string $affiliateId): array;
    public function get_adjustment(string $affiliateId, string $modelId): array;
    public function create_adjustment(string $affiliateId, array $payload): array;
    public function update_adjustment(string $affiliateId, string $modelId, array $payload): bool;
    public function delete_adjustment(string $affiliateId, string $modelId): bool;

    public function feed_chargebacks(string $affiliateId): array;
    public function get_chargebacks(string $affiliateId, string $chargebackId): array;
    public function create_chargebacks(string $affiliateId, array $payload): array;
    public function update_chargebacks(string $affiliateId, string $chargebackId): bool;
    public function delete_chargebacks(string $affiliateId, string $chargebackId): bool;
}
