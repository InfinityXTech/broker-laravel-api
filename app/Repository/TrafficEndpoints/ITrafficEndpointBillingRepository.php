<?php

namespace App\Repository\TrafficEndpoints;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ITrafficEndpointBillingRepository extends IRepository
{

    public function get_payment_methods(string $trafficEndpointId): array;
    public function get_payment_requests_for_chargeback(string $trafficEndpointId): array;

    public function set_manual_status(string $trafficEndpointId, string $manual_status): bool;

    public function feed_billing_general_balances(string $trafficEndpointId): array;
    public function feed_billing_balances_log(string $trafficEndpointId, int $page, int $count_in_page = 20): array;
    public function history_log_billing_general_balances(string $trafficEndpointId, array $payload): array;
    public function update_billing_balances_log(string $trafficEndpointId, string $logId, array $payload): bool;

    public function get_recalculate_logs(string $trafficEndpointId, array $payload): array;

    public function feed_billing_entities(string $trafficEndpointId): array;
    public function get_billing_entities(string $trafficEndpointId, string $entityId): ?Model;
    public function create_billing_entities(string $trafficEndpointId, array $payload): ?Model;
    public function update_billing_entities(string $trafficEndpointId, string $entityId, array $payload): bool;
    public function remove_billing_entities(string $trafficEndpointId, string $entityId): bool;

    public function feed_billing_payment_methods(string $trafficEndpointId): array;
    public function create_billing_payment_methods(string $trafficEndpointId, array $payload): string;
    public function update_billing_payment_methods($trafficEndpointId, $id, $payload): bool;
    public function active_billing_payment_methods(string $trafficEndpointId, string $paymentMethodId);

    public function files_billing_payment_methods(string $trafficEndpointId, string $paymentMethodId): array;

    public function feed_billing_payment_requests(string $trafficEndpointId): array;
    public function get_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId): array;
    public function get_billing_payment_request_view_calculation(string $trafficEndpointId, string $paymentRequestId): array;
    public function crg_details(string $trafficEndpointId, string $crgId, array $payload): array;
    public function pre_create_billing_payment_requests(string $trafficEndpointId, array $payload): array;
    public function create_billing_payment_requests(string $trafficEndpointId, array $payload): array;
    public function files_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId): array;
    public function approve_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array;
    public function reject_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array;
    public function master_approve_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array;
    public function real_income_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array;
    public function final_approve_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array;
    public function final_reject_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array;
    public function archive_rejected_billing_payment_requests(string $trafficEndpointId, string $paymentRequestId, array $payload): array;
    public function get_payment_request_invoice(string $trafficEndpointId, string $paymentRequestId);

    public function feed_completed_transactions(string $trafficEndpointId): array;

    public function feed_adjustments(string $trafficEndpointId): array;
    public function get_adjustment(string $trafficEndpointId, string $modelId): array;
    public function create_adjustment(string $trafficEndpointId, array $payload): array;
    public function update_adjustment(string $trafficEndpointId, string $modelId, array $payload): bool;
    public function delete_adjustment(string $trafficEndpointId, string $modelId): bool;

    public function feed_chargebacks(string $trafficEndpointId): array;
    public function get_chargebacks(string $trafficEndpointId, string $chargebackId): array;
    public function create_chargebacks(string $trafficEndpointId, array $payload): array;
    public function update_chargebacks(string $trafficEndpointId, string $chargebackId): bool;
    public function delete_chargebacks(string $trafficEndpointId, string $chargebackId): bool;
}
