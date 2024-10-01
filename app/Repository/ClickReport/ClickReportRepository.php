<?php

namespace App\Repository\ClickReport;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use App\Classes\ClickReport\ClickReportMeta;
use Illuminate\Database\Eloquent\Collection;
use App\Classes\ClickReport\ClickReportService;
use App\Repository\ClickReport\IClickReportRepository;

// use App\Classes\QualityReport;

class ClickReportRepository extends BaseRepository implements IClickReportRepository
{
    public function __construct()
    {
    }

    public function run(array $payload): array
    {
        $service = new ClickReportService($payload);
        return $service->Handler(); //collect
    }

    private function mofify_with_profile(string $name, array &$array) {
        $user = Auth::user();
        if ($array && $user) {
            $profile = $user->get_profile($name);
            if ($profile) {
                foreach($array as &$field) {
                    $field['selected'] = false;
                    if (in_array($field['value'], $profile)) {
                        $field['selected'] = true;
                    }
                }
                usort($array, function($key1, $key2) use ($profile) {
                    return (array_search($key1['value'], $profile) > array_search($key2['value'], $profile));
                });
            }
        }
    }

    public function pivot(): array
    {
        $result = [];

        $custom_allow = function ($key, $pivot) {
            if (Gate::has('custom:click_report[pivots.' . $key . ']')) {
                return Gate::allows('custom:click_report[pivots.' . $key . ']');
            }
            if (isset($pivot['allow']) && $pivot['allow'] === false) {
                if (Gate::has('custom:click_report[pivots.' . $key . ']')) {
                    return $pivot['visible'] && Gate::allows('custom:click_report[pivots.' . $key . ']');
                }
                return $pivot['visible'];
            }
            return false;
        };

        foreach (ClickReportMeta::$pivot_titles as $pivot_key => $pivot) {
            if (($pivot['visible'] ?? false) && (!isset($pivot['allow']) || $custom_allow($pivot_key, $pivot))) {

                // broker
                // if (in_array($pivot_key, []) && !Gate::allows('brokers[active=1]')) {
                //     continue;
                // }

                // traffic endpoint
                if (in_array($pivot_key, ['TrafficEndpoint']) && !Gate::allows('traffic_endpoint[active=1]')) {
                    continue;
                }

                $result[] = [
                    'value' => $pivot_key,
                    'label' => $pivot['title'],
                    'selected' => $pivot['selected']
                ];
            }
        }

        $this->mofify_with_profile('click_report_pivot', $result);

        return $result;
    }

    public function metrics(): array
    {
        $result = [];

        $custom_allow = function ($key, $pivot) {
            if (Gate::has('custom:click_report[metrics.' . $key . ']')) {
                return Gate::allows('custom:click_report[metrics.' . $key . ']');
            }
            if (isset($pivot['allow']) && $pivot['allow'] === false) {
                return Gate::allows('custom:click_report[metrics.' . $key . ']');
            }
            return true;
        };

        foreach (ClickReportMeta::$pivot_metrics as $pivot_key => $pivot) {
            if (($pivot['visible'] ?? false) || $custom_allow($pivot_key, $pivot)) {
                $result[] = [
                    'value' => $pivot_key,
                    'label' => $pivot['title'],
                    'selected' => $pivot['selected']
                ];
            }
        }

        $this->mofify_with_profile('click_report_metrics', $result);

        return $result;
    }

    public function download(array $payload): array
    {
        $payload['download'] = 'csv';
        $service = new ClickReportService($payload);
        return $service->Handler(); //collect
    }
}
