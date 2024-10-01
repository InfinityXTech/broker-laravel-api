<?php

namespace App\Repository\Leads;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ILeadsRepository extends IRepository
{
	public function test_lead(string $leadId): bool;
	public function createAlerts(string $leadId, array $payload): bool;
	public function deleteAlerts(string $leadId): bool;
	public function listAlerts(): Collection;
	public function approve(string $leadId): bool;
	public function fireftd(string $leadId, bool $fake_deposit = false): bool;
	public function get_crg_lead(string $leadId): array;
	public function mark_crg_lead(string $leadId, array $payload): bool;
	public function get_crg_ftd(string $leadId): array;
	public function change_crg_ftd(string $leadId, array $payload): bool;
	public function get_payout(string $leadId): array;
	public function update_payout(string $leadId, array $payload): bool;
	public function test_lead_data(array $payload): array;
	public function test_lead_send(array $payload): array;
	public function get_change_payout_cpl_lead(string $leadId): array;
	public function post_change_payout_cpl_lead(string $leadId, array $payload): bool;
}
