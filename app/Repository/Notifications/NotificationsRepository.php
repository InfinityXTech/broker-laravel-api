<?php

namespace App\Repository\Notifications;

use App\Helpers\SystemHelper;
use App\Models\Notifications;
use App\Models\LeadsReviewSupport;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Gravity\GravityRepository;
use App\Repository\Affiliates\AffiliateRepository;
use App\Repository\Notifications\INotificationsRepository;
use App\Repository\TrafficEndpoints\TrafficEndpointRepository;
use App\Repository\MarketingGravity\MarketingGravityRepository;

class NotificationsRepository extends BaseRepository implements INotificationsRepository
{

    /**
     * BaseRepository constructor.
     */
    public function __construct()
    {
    }

    public function notifications(bool $with_access = true): array
    {
        $notifications = [];

        // рабочая версия, пока их нет
        // $te_rep = new TrafficEndpointRepository();
        // $stat_under_review = $te_rep->stat_under_review();
        // if (Gate::allows('role:admin') || !$with_access) {
        //     if (($stat_under_review['under_review'] ?? 0) > 0) {
        //         $notifications[] = [
        //             "permissions" => ['role:admin'],
        //             "link" => '/traffic_endpoints/application',
        //             "info" => [
        //                 "name" => 'Application Traffic Endpoints: ',
        //                 "value" => $stat_under_review['under_review'] ?? 0
        //             ],
        //             "level" => 'dangerous',
        //             "icon" => '<i class="uit uit-jackhammer sidebar-svg"></i>'
        //             // time: new Date(),
        //         ];
        //     }
        // }

        $systemId = SystemHelper::systemId();
        switch ($systemId) {
            case 'marketing': {
                    $te_rep = new AffiliateRepository();
                    $stat_under_review = $te_rep->stat_under_review();
                    if (Gate::allows('role:admin') || !$with_access) {
                        if (($stat_under_review['under_review'] ?? 0) > 0) {
                            $notifications[] = [
                                "permissions" => ['role:admin'],
                                "link" => '/affiliates/application',
                                "info" => [
                                    "name" => 'Application Affiliate: ',
                                    "value" => $stat_under_review['under_review'] ?? 0
                                ],
                                "level" => 'dangerous',
                                "icon" => '<i class="uit uit-plug sidebar-svg"></i>'
                                // time: new Date(),
                            ];
                        }
                    }

                    if (Gate::allows('marketing_gravity[active=1]') || !$with_access || true) {
                        $gr_rep = new MarketingGravityRepository();
                        $lead_allows = [
                            'auto' => ['key' => 1, 'title' => 'Auto Approve'],
                            'manual' => ['key' => 2, 'title' => 'Manual Approve'],
                            'tech' => ['key' => 3, 'title' => 'Tech Issue'],
                            'high' => ['key' => 4, 'title' => 'High Risk FTD’s'],
                            'financial' => ['key' => 5, 'title' => 'Financial']
                        ];
                        foreach ($lead_allows as $type => $a) {
                            $leads = $gr_rep->leads($a['key']);
                            if (count($leads ?? []) > 0) {
                                $notifications[] = [
                                    // "permissions" => ['marketing_gravity[active=1]'],
                                    "permissions" => [],
                                    "link" => '/marketing_gravity/' . $type,
                                    "info" => [
                                        "name" => 'Gravity: ' . $a['title'] . ': ',
                                        "value" => count($leads)
                                    ],
                                    "level" => 'dangerous',
                                    "icon" => '<i aria-hidden="true" class="lnir lnir-postcard"></i>'
                                    // time: new Date(),
                                ];
                            }
                        }
                    }
                    break;
                }
            case 'crm': {
                    if (Gate::allows('gravity[active=1]') || !$with_access) {
                        $gr_rep = new GravityRepository();
                        $lead_allows = [
                            'auto' => ['key' => 1, 'title' => 'Auto Approve'],
                            'manual' => ['key' => 2, 'title' => 'Manual Approve'],
                            'tech' => ['key' => 3, 'title' => 'Tech Issue'],
                            'high' => ['key' => 4, 'title' => 'High Risk FTD’s']
                        ];
                        foreach ($lead_allows as $type => $a) {
                            $leads = $gr_rep->leads($a['key']);
                            if (count($leads ?? []) > 0) {
                                $notifications[] = [
                                    "permissions" => ['gravity[active=1]'],
                                    "link" => '/gravity/' . $type,
                                    "info" => [
                                        "name" => 'Gravity: ' . $a['title'] . ': ',
                                        "value" => count($leads)
                                    ],
                                    "level" => 'dangerous',
                                    "icon" => '<i aria-hidden="true" class="lnir lnir-postcard"></i>'
                                    // time: new Date(),
                                ];
                            }
                        }
                    }

                    if (Gate::allows('leads_review_support[active=1]') || !$with_access) {
                        $count = LeadsReviewSupport::query()->whereIn('status', ['1', 1])->get(['_id'])->count();
                        if ($count > 0) {
                            $notifications[] = [
                                "permissions" => ['leads_review_support[active=1]'],
                                "link" => '/leads_review_support',
                                "info" => [
                                    "name" => 'Leads Review Tickets: ',
                                    "value" => $count
                                ],
                                "level" => 'dangerous',
                                "icon" => '<i aria-hidden="true" class="uit uit-rocket sidebar-svg"></i>'
                                // time: new Date(),
                            ];
                        }
                    }
                    break;
                }
        }

        return $notifications;
    }
}
