<?php

namespace App\Repository\Report;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Classes\Report\ReportMeta;

use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Report\ReportService;
use App\Helpers\GeneralHelper;
use Illuminate\Database\Eloquent\Model;
use App\Repository\Report\IReportRepository;
use Illuminate\Database\Eloquent\Collection;

// use App\Classes\QualityReport;

class ReportRepository extends BaseRepository implements IReportRepository
{
    public function __construct()
    {
    }

    public function run(array $payload): array
    {
        $service = new ReportService($payload);
        return $service->Handler(); //collect
    }

    public function download(array $payload): array
    {
        $payload['download'] = 'csv';
        $service = new ReportService($payload);
        return $service->Handler(); //collect
    }

    private function modify_with_profile(string $name, array &$array)
    {
        $user = Auth::user();
        if ($array && $user) {
            $profile = $user->get_profile($name);
            if ($profile) {
                foreach ($array as &$field) {
                    $field['selected'] = false;
                    if (in_array($field['value'], $profile)) {
                        $field['selected'] = true;
                    }
                }
                usort($array, function ($key1, $key2) use ($profile) {
                    return (array_search($key1['value'], $profile) > array_search($key2['value'], $profile));
                });
            }
        }
    }

    public function pivot(): array
    {
        $result = [];

        $custom_allow = function ($key, $pivot) {
            if (Gate::has('custom:reports[pivots.' . $key . ']')) {
                return Gate::allows('custom:reports[pivots.' . $key . ']');
            }
            if (isset($pivot['allow']) && $pivot['allow'] === false) {
                if (Gate::has('custom:reports[pivots.' . $key . ']')) {
                    return $pivot['visible'] && Gate::allows('custom:reports[pivots.' . $key . ']');
                }
                return $pivot['visible'];
            }

            return false;
        };

        foreach (ReportMeta::$pivot_titles as $pivot_key => $pivot) {
            if (($pivot['visible'] ?? false) && (!isset($pivot['allow']) || $custom_allow($pivot_key, $pivot))) {

                // broker
                if (in_array($pivot_key, ['brokerId', 'broker_status', 'broker_lead_id', 'broker_crg_percentage_id']) && !Gate::allows('brokers[active=1]')) {
                    continue;
                }

                // traffic endpoint
                if (in_array($pivot_key, ['TrafficEndpoint', 'crg_percentage_id']) && !Gate::allows('traffic_endpoint[active=1]')) {
                    continue;
                }

                // masters
                if (in_array($pivot_key, ['MasterAffiliate', 'master_brand']) && !Gate::allows('masters[active=1]')) {
                    continue;
                }

                $result[] = [
                    'value' => $pivot_key,
                    'label' => $pivot['title'],
                    'selected' => $pivot['selected']
                ];
            }
        }

        $this->modify_with_profile('report_pivot', $result);
        return $result;
    }

    public function metrics(): array
    {
        $result = [];

        $custom_allow = function ($key, $pivot) {
            if (Gate::has('custom:reports[metrics.' . $key . ']')) {
                return Gate::allows('custom:reports[metrics.' . $key . ']');
            }
            if (isset($pivot['allow']) && $pivot['allow'] === false) {
                return Gate::allows('custom:reports[metrics.' . $key . ']');
            }
            return true;
        };

        foreach (ReportMeta::$pivot_metrics as $pivot_key => $pivot) {
            if (($pivot['visible'] ?? false) || $custom_allow($pivot_key, $pivot)) {

                // broker
                if (in_array($pivot_key, ['broker_crg_already_paid_ftd', 'broker_crg_leads', 'broker_cpl_leads']) && !Gate::allows('brokers[active=1]')) {
                    continue;
                }

                // traffic endpoint
                if (in_array($pivot_key, ['crg_leads']) && !Gate::allows('traffic_endpoint[active=1]')) {
                    continue;
                }

                // masters
                if (in_array($pivot_key, ['affiliate_cost', 'master_affiliate_payout', 'master_brand_payout']) && !Gate::allows('masters[active=1]')) {
                    continue;
                }

                // fraud
                if (in_array($pivot_key, ['fraudHighRisk', 'fraudMediumRisk', 'fraudLowRisk']) && !Gate::allows('crm[fraud_detection=1]')) {
                    continue;
                }

                $result[] = [
                    'value' => $pivot_key,
                    'label' => $pivot['title'],
                    'selected' => $pivot['selected']
                ];
            }
        }

        $this->modify_with_profile('report_metrics', $result);
        return $result;
    }
}
