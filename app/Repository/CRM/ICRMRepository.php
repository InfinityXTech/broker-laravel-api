<?php

namespace App\Repository\CRM;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ICRMRepository extends IRepository {
    public function all_leads(array $payload): array;
	public function deposits(array $payload): array;
	public function mismatch(array $payload): array;
	
	public function status_lead_history(string $leadId): array;
	public function reject(array $payload): array;
	public function approve(array $payload): array;
	public function get_resync(array $ids): array;
	public function resync(array $payload): array;
	public function download_recalculation_changes_log(array $payload): string;
}