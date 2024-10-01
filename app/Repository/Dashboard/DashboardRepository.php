<?php

namespace App\Repository\Dashboard;

// use App\Models\TrafficEndpoint;
// use App\Models\Offer;

use App\Helpers\GeneralHelper;
use App\Models\Stats;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Dashboard\IDashboardRepository;

// use App\Classes\QualityReport;

class DashboardRepository extends BaseRepository implements IDashboardRepository
{
    public function __construct()
    {
    }

    public function index(): array
    {
        // $d = Stats::findOrFail('5f635ad873b1ca0d56136a70')->toArray();
        $stat = Stats::query()->first();

        if ($stat) {

            $d = $stat->toArray();

            $hourly_stats = [];
            if (isset($d['GraphHourlyDashboard'])) {
                $hourly_stats = json_decode($d['GraphHourlyDashboard'], true);
            }
            $h = date('H');
            $count_today = 0;
            $today_string = '';

            $hrs_string = '';
            $hrs_status = false;

            if (isset($hourly_stats['today'])) {
                foreach ($hourly_stats['today'] as $hr => $counter) {

                    if ($hrs_status == false) {
                        if ($hr == $h) {
                            $hrs_status = true;
                            // $hrs_string .= "'" . $hr . "'";
                            $hrs_string .= $hr;
                        } else {
                            // $hrs_string .= "'" . $hr . "',";
                            $hrs_string .= $hr . ",";
                        }
                    }
                }
            }

            $hrs_today_status = false;
            if (isset($hourly_stats['today'])) {
                foreach ($hourly_stats['today'] as $hk => $today) {
                    $count_today = $count_today + 1;

                    if ($hrs_today_status == false) {
                        if ($hk >= $h) {
                            $today_string .= (int)$today;
                            $hrs_today_status = true;
                        } else {
                            $today_string .= (int)$today . ',';
                        }
                    }
                }
            }

            $count_yesterday = 0;
            $yesterday_string = '';
            $hrs_yesterday_status = false;
            if (isset($hourly_stats['yesterday'])) {
                foreach ($hourly_stats['yesterday'] as $tk => $yesterday) {
                    $count_yesterday = $count_yesterday + 1;

                    if ($hrs_yesterday_status == false) {
                        if ($tk >= $h) {
                            $hrs_yesterday_status = true;
                            $yesterday_string .= (int)$yesterday;
                        } else {
                            $yesterday_string .= (int)$yesterday . ',';
                        }
                    }
                }
            }


            $country_stats = [];
            if (isset($d['GraphCountryDashboard'])) {
                $country_stats = json_decode($d['GraphCountryDashboard'], true);
            }
            $country_list_string = '';
            $country_today_string = '';
            $country_yesterday_string = '';

            $count = count($country_stats);
            $counter_geo = 0;
            foreach ($country_stats as $country => $country_data) {
                $counter_geo = $counter_geo + 1;

                if ($counter_geo >= $count) {
                    $country_list_string .= strtoupper($country);
                    // $country_list_string .= "'" . $country . "'";
                    $country_today_string .= $country_data['today'];
                    $country_yesterday_string .= $country_data['yesterday'];
                } else {
                    // $country_list_string .= "'" . $country . "',";
                    $country_list_string .= strtoupper($country) . ",";
                    $country_today_string .= $country_data['today'] . ',';
                    $country_yesterday_string .= $country_data['yesterday'] . ',';
                }
            }

            return [
                [
                    'today' => $today_string,
                    'yesterday' => $yesterday_string,
                    'by' => 'hours',
                    'hours' => $hrs_string,
                    'title' => 'Comparing Hourly Lead pacing'
                ],
                [
                    'today' => $country_today_string,
                    'yesterday' => $country_yesterday_string,
                    'by' => 'countries',
                    'countries' => $country_list_string,
                    'title' => 'Comparing Country Scale'
                ]
            ];
        }

        return [];
    }
}
