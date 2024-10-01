<?php

namespace App\Repository\Performance;

use App\Classes\Performance\DeepDive;
use App\Classes\Performance\Download;
use App\Classes\Performance\General;
use App\Classes\Performance\Performance;
use App\Models\PerformanceBrokerStatuses;
use App\Repository\BaseRepository;
use App\Repository\Performance\IPerformanceRepository;

class PerformanceRepository extends BaseRepository implements IPerformanceRepository
{
    public function __construct()
    {
    }

    public function general(array $payload): array
    {
        $general = new General();
        return [
            'endpoints' => $general->endpoints($payload),
            'brokers' => $general->brokers($payload),
        ];
    }

    public function traffic_endpoints(array $payload): array
    {
        $performance = new Performance();
        return $performance->endpoints($payload['timeframe'], $payload['endpointId'] ?? null, $payload['country_code'] ?? null, $payload['language_code'] ?? null);
    }

    public function brokers(array $payload): array
    {
        $performance = new Performance();
        return $performance->brokers($payload['timeframe'], $payload['brokerId'] ?? null, $payload['country_code'] ?? null, $payload['language_code'] ?? null);
    }

    public function vendors(array $payload): array
    {
        $performance = new Performance();
        return $performance->vendors($payload['timeframe'], $payload['apivendorId'] ?? null, $payload['country_code'] ?? null, $payload['language_code'] ?? null);
    }

    public function deep_dive(array $payload): array
    {
        $country_code = $payload['country_code'] ?? null;
        $language_code = $payload['language_code'] ?? null;
        $brokerId = $payload['brokerId'] ?? null;
        $endpointId = $payload['endpointId'] ?? null;
        $apivendorId = $payload['apivendorId'] ?? null;
        $error_type = $payload['error_type'] ?? null;
        $timeframe = $payload['timeframe'] ?? null;
        $deepdive = new DeepDive($brokerId, $endpointId, $apivendorId, $timeframe, $country_code, $language_code, $error_type);
        return [
            'general' => $deepdive->general($payload),
            'country' => $deepdive->country($payload),
            'vendor'  => $deepdive->vendor($payload),
        ];
    }

    public function download(array $payload): string
    {
        $country_code = $payload['country_code'] ?? null;
        $language_code = $payload['language_code'] ?? null;
        $brokerId = $payload['brokerId'] ?? null;
        $endpointId = $payload['endpointId'] ?? null;
        $apivendorId = $payload['apivendorId'] ?? null;
        $error_type = $payload['error_type'] ?? null;
        $error_message = $payload['error_message'] ?? null;
        $timeframe = $payload['timeframe'] ?? null;
        $download = new Download();
        return $download->run($brokerId, $endpointId, $apivendorId, $timeframe, $country_code, $language_code, $error_type, $error_message);
    }

    public function settings_broker_statuses_all(): array
    {
        return PerformanceBrokerStatuses::all()->toArray();
    }

    public function settings_broker_statuses_get(string $id): array
    {
        return PerformanceBrokerStatuses::findOrFail($id)->toArray();
    }

    public function settings_broker_statuses_delete(string $id): bool
    {
        if (PerformanceBrokerStatuses::findOrFail($id)->delete()) {
            return true;
        }
        return false;
    }

    public function settings_broker_statuses_update(string $id, array $payload): bool
    {
        $model = PerformanceBrokerStatuses::findOrFail($id);
        return $model->update($payload);
    }

    public function settings_broker_statuses_create(array $payload): bool
    {
        $model = new PerformanceBrokerStatuses();
        $model->fill($payload);
        return $model->save();
    }
}
