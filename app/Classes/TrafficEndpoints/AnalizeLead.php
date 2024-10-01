<?php

namespace App\Classes\TrafficEndpoints;

use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use App\Helpers\CryptHelper;
use App\Helpers\GeneralHelper;

class AnalizeLead
{

    private function checkLeadCampaign($lead)
    {
        /*
        first category campaign
        if the campaign is empty or not
        if not empty if campaign exist
        3.if exist if campaign active
        */

        $result = [];
        if (!isset($lead['CampaignId']) || (isset($lead['CampaignId']) && empty($lead['CampaignId']))) {
            $result[] = ['success' => false, 'error' => 'Campaign is empty or not set'];
        }

        if (isset($lead['CampaignId']) && !empty($lead['CampaignId'])) {
            $where['_id'] = new \MongoDB\BSON\ObjectId($lead['CampaignId']);
            $mongo = new MongoDBObjects('campaigns', $where);
            $data = $mongo->find();
            if (!isset($data['_id'])) {
                $result[] = ['success' => false, 'error' => 'Campaign is set but not found'];
            } else {
                if ($data['status'] != 1) {
                    $result[] = ['success' => false, 'error' => 'Campaign is not active'];
                }
            }
        }

        if (count($result) == 0) {
            $result[] = ['success' => true, 'message' => 'Campaign: passed'];
        }

        return $result;
    }

    private function checkLeadEndpoint($lead)
    {
        /*
        second category endpoint -
        1.if endpoint exist
        2.if endpoint is active
        */

        $result = [];
        if (!isset($lead['TrafficEndpoint']) || (isset($lead['TrafficEndpoint']) && empty($lead['TrafficEndpoint']))) {
            $result[] = ['success' => false, 'error' => 'Traffic Endpoint is empty or not set'];
        }

        if (isset($lead['TrafficEndpoint']) && !empty($lead['TrafficEndpoint'])) {
            $where['_id'] = new \MongoDB\BSON\ObjectId($lead['TrafficEndpoint']);
            $mongo = new MongoDBObjects('TrafficEndpoints', $where);
            $data = $mongo->find();
            if (!isset($data['_id'])) {
                $result[] = ['success' => false, 'error' => 'Traffic Endpoint is set but not found'];
            } else {
                if ($data['status'] != 1) {
                    $result[] = ['success' => false, 'error' => 'Traffic Endpoint is not active'];
                }
            }
        }

        if (count($result) == 0) {
            $result[] = ['success' => true, 'message' => 'Traffic Endpoint: passed'];
        }

        return $result;
    }

    private function checkLeadGeneral($lead)
    {
        /*
        third category General data
        check that the country is valid and exist
        check that language is valid and exist
        we received IP and IP is valid
        we received phone and phone is valid
        we received email and email is valid
        we received funnel_lp and funnel_lp is valid
        */

        $result = [];

        //Country
        if (!isset($lead['country']) || (isset($lead['country']) && empty($lead['country']))) {
            $result[] = ['success' => false, 'error' => 'General data: Country is empty'];
        }

        //Language
        if (!isset($lead['language']) || (isset($lead['language']) && empty($lead['language']))) {
            $result[] = ['success' => false, 'error' => 'General data: Language is empty'];
        }

        //Link of Funnel LP
        if (!isset($lead['funnel_lp']) || (isset($lead['funnel_lp']) && empty($lead['funnel_lp']))) {
            $result[] = ['success' => false, 'error' => 'General data: Link of Funnel LP is empty'];
        }
        if (!filter_var($lead['funnel_lp'], FILTER_VALIDATE_URL)) {
            $result[] = ['success' => false, 'error' => 'General data: Link of Funnel LP "' . $lead['funnel_lp'] . '" is not valid'];
        }

        // IP
        if (!isset($lead['ip']) || (isset($lead['ip']) && empty($lead['ip']))) {
            $result[] = ['success' => false, 'error' => 'General data: IP is empty'];
        }
        if (!filter_var($lead['ip'], FILTER_VALIDATE_IP)) {
            $result[] = ['success' => false, 'error' => 'General data: IP "' . $lead['ip'] . '" is not valid'];
        }

        //Email
        if (!isset($lead['email']) || (isset($lead['email']) && empty($lead['email']))) {
            $result[] = ['success' => false, 'error' => 'General data: Email is empty'];
        }
        if (!filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
            $result[] = ['success' => false, 'error' => 'General data: Email "' . $lead['email'] . '" is not valid'];
        }

        if (count($result) == 0) {
            $result[] = ['success' => true, 'message' => 'General data: passed'];
        }

        return $result;
    }

    private function checkLeadPerformance($lead)
    {

        /*
        fourth category Performance -

        we received a subchannel and the subchannel is not empty
        we received a creative id and the creative id is not empty
        we received a clicktoken and the clicktoken is not empty        
        */
        $result = [];

        //subchannel
        if (!isset($lead['subchannel']) || (isset($lead['subchannel']) && empty($lead['subchannel']))) {
            $result[] = ['success' => false, 'error' => 'Performance: subchannel is empty'];
        }

        //creative_id
        if (!isset($lead['creative_id']) || (isset($lead['creative_id']) && empty($lead['creative_id']))) {
            $result[] = ['success' => false, 'error' => 'Performance: creative_id is empty'];
        }

        //clicktoken the same publisher_click
        if (!isset($lead['publisher_click']) || (isset($lead['publisher_click']) && empty($lead['publisher_click']))) {
            $result[] = ['success' => false, 'error' => 'Performance: clicktoken is empty'];
        }

        if (count($result) == 0) {
            $result[] = ['success' => true, 'message' => 'Performance: Performance: passed'];
        }

        return $result;
    }

    public function checkLead($lead_id)
    {
        try {

            if (empty($lead_id)) throw new \Exception("Lead is required");

            $where = ['_id' => new \MongoDB\BSON\ObjectId($lead_id)];

            $mongo = new MongoDBObjects('leads', $where);

            $lead = $mongo->find();

            // --- Decrypt --- //
            CryptHelper::decrypt_lead_data_array($lead);

            if (!isset($lead['_id'])) throw new \Exception("Lead '" . $lead_id . "' is not found");

            $result = [];

            //check campaign
            // $result = array_merge($result, $this->checkLeadCampaign($lead));

            //check traffic endpoint
            $result = array_merge($result, $this->checkLeadEndpoint($lead));

            //check general data
            $result = array_merge($result, $this->checkLeadGeneral($lead));

            //check performance
            $result = array_merge($result, $this->checkLeadPerformance($lead));

            // $html = '<ul>';
            // foreach ($result as $r) {
            //     $html .= '<li>
            //                     <div class="alert alert-' . ($r['success'] ? 'success' : 'danger') . '" role="alert">
            //                         ' . ($r['success'] == true ? $r['message'] : $r['error']) . '
            //                     </div>
            //                 </li>';
            // }

            //$html .= '<hr/><pre>' . print_r($result, true) . '</pre>';

            // if (auth::is_current_user_admin() || permissionsManagement::is_current_user_role('tech_support')) {
            //     $html .= '<hr/><b>Only for admin & support</b><pre>' . print_r($lead, true) . '</pre>';
            // }

            $lead_html = '';
            $lead_json = '';
            if (Gate::allows('role:tech_support')) {
                $lead_html = print_r($lead, true);
                $lead_json = json_decode(json_encode($lead), true);
                foreach ($lead_json as $k => &$l) {
                    if (is_array($l) && isset($l['$date'])) {
                        $l = GeneralHelper::GeDateFromTimestamp($l, "Y-m-d H:i:s");
                    } else
                    if ($k == 'log_post' || $k == 'log_server') {
                        $d = json_decode($l, true);
                        if (json_last_error() == JSON_ERROR_NONE && !empty($d)) {
                            $l = $d;
                        }
                    } else
                    if ($k == '_id') {
                        $l = $l['$oid'];
                    }
                }
                // $lead_html = json_encode($lead);
            }

            $data = ['items' => $result, 'lead' => $lead_json, 'lead_html' => $lead_html];
        } catch (\Exception $ex) {
            $data = ['success' => false, 'error' => $ex->getMessage()];
        }
        return $data;
    }
}
