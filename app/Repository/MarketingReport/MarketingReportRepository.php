<?php

namespace App\Repository\MarketingReport;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Classes\MarketingReport\MarketingReportMeta;

use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\MarketingReport\MarketingReportService;
use App\Helpers\GeneralHelper;
use Illuminate\Database\Eloquent\Model;
use App\Repository\MarketingReport\IMarketingReportRepository;
use Illuminate\Database\Eloquent\Collection;

// use App\Classes\QualityReport;

class MarketingReportRepository extends BaseRepository implements IMarketingReportRepository
{
    public function __construct()
    {
    }

    public function run(array $payload): array
    {
        $service = new MarketingReportService($payload);
        return $service->Handler(); //collect
    }

    public function download(array $payload): array
    {
        $payload['download'] = 'csv';
        $service = new MarketingReportService($payload);
        return $service->Handler(); //collect
    }

    private function modify_with_profile(string $name, array &$array) {
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
            if (Gate::has('custom:marketing_reports[pivots.' . $key . ']')) {
                return Gate::allows('custom:marketing_reports[pivots.' . $key . ']');
            }
            if (isset($pivot['allow']) && $pivot['allow'] === false) {
                if (Gate::has('custom:marketing_reports[pivots.' . $key . ']')) {
                    return $pivot['visible'] && Gate::allows('custom:marketing_reports[pivots.' . $key . ']');
                }
                return $pivot['visible'];
            }
            return false;
        };

        foreach (MarketingReportMeta::$pivot_titles as $pivot_key => $pivot) {
            if (($pivot['visible'] ?? false) && (!isset($pivot['allow']) || $custom_allow($pivot_key, $pivot))) {
                $result[] = [
                    'value' => $pivot_key,
                    'label' => $pivot['title'],
                    'selected' => $pivot['selected']
                ];
            }
        }

        $this->modify_with_profile('marketing_report_pivot', $result);
        return $result;
    }

    public function metrics(): array
    {
        $result = [];
    
        $custom_allow = function ($key, $pivot) {
            if (Gate::has('custom:marketing_reports[metrics.' . $key . ']')) {
                return Gate::allows('custom:marketing_reports[metrics.' . $key . ']');
            }
            if (isset($pivot['allow']) && $pivot['allow'] === false) {
                return Gate::allows('custom:marketing_reports[metrics.' . $key . ']');
            }
            return true;
        };
        
        foreach (MarketingReportMeta::$pivot_metrics as $pivot_key => $pivot) {
            if (($pivot['visible'] ?? false) || $custom_allow($pivot_key, $pivot)) {
                $result[] = [
                    'value' => $pivot_key,
                    'label' => $pivot['title'],
                    'selected' => $pivot['selected']
                ];
            }
        }
       
        $this->modify_with_profile('marketing_report_metrics', $result);
        return $result;
    }
}
