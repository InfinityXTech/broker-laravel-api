<?php

namespace App\Repository\QualityReport;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Repository\BaseRepository;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\QualityReport\QualityReportMeta;
use App\Classes\QualityReport\QualityReportService;
use App\Repository\QualityReport\IQualityReportRepository;
use Illuminate\Support\Facades\Log;

// use App\Classes\Planning;

class QualityReportRepository extends BaseRepository implements IQualityReportRepository
{
    public function __construct()
    {
    }

    public function run(array $payload): array
    {
        $service = new QualityReportService($payload);
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

        $user = Auth()->user();
        $custom_allow = function ($key, $pivot) use ($user) {
            // $allow_fields = [''];
            // if ($user['account_email'] == 'mike@ppcnation.media' && in_array($key, $allow_fields)){
            //     return true;
            // }
            // return false;
            return true;
        };

        foreach (QualityReportMeta::$pivot_titles as $pivot_key => $pivot)
            if ($pivot['visible']) {  // || $custom_allow($pivot_key, $pivot)

                // broker
                if (in_array($pivot_key, ['brokerId']) && !Gate::allows('brokers[active=1]')) {
                    continue;
                }

                // traffic endpoint
                if (in_array($pivot_key, ['TrafficEndpoint']) && !Gate::allows('traffic_endpoint[active=1]')) {
                    continue;
                }

                // masters
                // if (in_array($pivot_key, []) && !Gate::allows('masters[active=1]')) {
                //     continue;
                // }

                $result[] = [
                    'value' => $pivot_key,
                    'label' => $pivot['title'],
                    'selected' => ($pivot['default_selected'] ?? false)
                ];
            }

        $this->modify_with_profile('quality_report_pivot', $result);

        return $result;
    }

    public function metrics(): array
    {
        $result = [];

        $custom_allow = function ($key, $pivot) {
            if (Gate::has('custom:quality_report[metrics.' . $key . ']')) {
                return Gate::allows('custom:quality_report[metrics.' . $key . ']');
            }
            if (isset($pivot['allow']) && $pivot['allow'] === false) {
                return Gate::allows('custom:quality_report[metrics.' . $key . ']');
            }
            return true;
        };

        foreach (QualityReportMeta::$pivot_metrics as $pivot_key => $pivot) {
            if (($pivot['visible'] ?? false) || $custom_allow($pivot_key, $pivot)) {

                // broker
                // if (in_array($pivot_key, []) && !Gate::allows('brokers[active=1]')) {
                //     continue;
                // }

                // traffic endpoint
                // if (in_array($pivot_key, []) && !Gate::allows('traffic_endpoint[active=1]')) {
                //     continue;
                // }

                // masters
                // if (in_array($pivot_key, []) && !Gate::allows('masters[active=1]')) {
                //     continue;
                // }

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

        $this->modify_with_profile('quality_report_metrics', $result);
        return $result;
    }

    public function download(array $payload): array
    {
        $payload['download'] = 'csv';
        $service = new QualityReportService($payload);
        return $service->Handler(); //collect
    }
}
