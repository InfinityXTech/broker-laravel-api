<?php

namespace App\Repository\EmailsChecker;

use XLSXWriter;
use MongoDB\BSON\ObjectId;

use App\Helpers\CryptHelper;
use App\Helpers\GeneralHelper;
use App\Repository\BaseRepository;
use App\Classes\Mongo\MongoDBObjects;
use App\Repository\EmailsChecker\IEmailsCheckerRepository;

class EmailsCheckerRepository extends BaseRepository implements IEmailsCheckerRepository
{
    public function __construct()
    {
    }

    private function get_leads_by_emails($emails_str)
    {
        $emails_str = str_replace(",", PHP_EOL, $emails_str);
        $emails_str = str_replace(" ", PHP_EOL, $emails_str);

        $emails = preg_split("/(\r\n|\n|\r)/", $emails_str);
        $in = [];

        $errors = '';
        foreach ($emails as $email) {
            if (!empty($email)) {
                try {
                    $in[] = new \MongoDB\BSON\Regex(preg_quote(trim(CryptHelper::encrypt($email))), "i");
                } catch (\Exception $ex) {
                    $errors .= '<br/>' . $email . ' is not valid';
                }
            }
        }

        if (!empty($errors)) {
            throw new \Exception('Operation aborted. Fix leads with errors: ' . $errors);
        }

        $where = array();
        $where = ['email' => ['$in' => $in]];
        $mongo = new MongoDBObjects('leads', $where);
        $leads = $mongo->findMany();
        foreach($leads as &$lead) {
            CryptHelper::decrypt_lead_data_array($lead);
        }
        return $leads;
    }

    private function get_leads_by_ids($ids_str)
    {
        $ids_str = str_replace(",", PHP_EOL, $ids_str);
        $ids_str = str_replace(" ", PHP_EOL, $ids_str);

        $ids = preg_split("/(\r\n|\n|\r)/", $ids_str);

        $errors = '';
        $in = array_map(function ($id) use ($errors) {
            try {
                return new ObjectId($id);
            } catch (\Exception $ex) {
                $errors .= '<br/>' . $id . ' is not valid';
            }
        }, $ids);

        if (!empty($errors)) {
            throw new \Exception('Operation aborted. Fix leads with errors: ' . $errors);
        }

        $where = array();
        $where = ['_id' => ['$in' => $in]];
        $mongo = new MongoDBObjects('leads', $where);
        $leads = $mongo->findMany();
        foreach($leads as &$lead) {
            CryptHelper::decrypt_lead_data_array($lead);
        }
        return $leads;
    }

    private function get_leads_by_broker_lead_ids($ids_str)
    {
        $ids_str = str_replace(",", PHP_EOL, $ids_str);
        $ids_str = str_replace(" ", PHP_EOL, $ids_str);

        $ids = preg_split("/(\r\n|\n|\r)/", $ids_str);

        $errors = '';
        foreach ($ids as $id) {
            if (!empty($id)) {
                try {
                    $in[] = new \MongoDB\BSON\Regex(preg_quote(trim($id)), "i");
                } catch (\Exception $ex) {
                    $errors .= '<br/>' . $id . ' is not valid';
                }
            }
        }

        if (!empty($errors)) {
            throw new \Exception('Operation aborted. Fix leads with errors: ' . $errors);
        }

        $where = array();
        $where = ['broker_lead_id' => ['$in' => $in]];
        $mongo = new MongoDBObjects('leads', $where);
        $leads = $mongo->findMany();
        foreach($leads as &$lead) {
            CryptHelper::decrypt_lead_data_array($lead);
        }
        return $leads;
    }

    public function run(array $payload): string
    {
        if ($payload['type'] == 'emails') {
            $leads = $this->get_leads_by_emails($payload['data']);
        } else if ($payload['type'] == 'leads') {
            // $leads = $this->get_leads_by_ids($payload['data']);
            $leads = $this->get_leads_by_broker_lead_ids($payload['data']);
        } else {
            throw new \Exception("Unknown type: " . $payload['type']);
        }

        $brokers = $this->get_broker_names();
        $integrations = $this->get_broker_integrations_names();
        $endpoints = $this->get_endpoints_names();

        $group = [];
        foreach ($leads as $lead) {
            $brokerId = $lead['brokerId'] ?? 'Unknown';
            if (!isset($group[$brokerId])) {
                $group[$brokerId] = [];
            }
            $group[$brokerId][] = $lead;
        }

        $writer = new XLSXWriter();
        $header = [
            //'Lead ID' => 'string',
            'Broker Lead ID' => 'string',
            'Timestamp' => 'string',
            'Integration' => 'string',
            'Country' => 'string',
            'Email' => 'string',
            'Broker Status' => 'string',
            'Endpoint' => 'string',
        ];

        $broker_name = 'NoOne';
        if (count($group) > 0) {
            foreach ($group as $brokerId => $leads) {
                $broker_name = $brokers[$brokerId] ?? 'Unknown';
                $data = [];
                foreach ($leads as $lead) {
                    try {
                        $id = MongoDBObjects::get_id($lead);
                        $timestamp = MongoDBObjects::get_timestamp($lead['Timestamp'], 'Y-m-d H:i:s');
                        $integration = isset($lead['integrationId']) && isset($integrations[$lead['integrationId']]) ? $integrations[$lead['integrationId']] : '';
                        $broker_lead_id = $lead['broker_lead_id'] ?? '';
                        $country = strtoupper($lead['country'] ?? '');
                        // $country_language = strtoupper($lead['country'] ?? '') . '_' . strtolower($lead['language'] ?? '');
                        $email = $lead['email'] ?? '';
                        $status = $lead['broker_status'] ?? '';
                        $endpoint = isset($lead['TrafficEndpoint']) && isset($endpoints[$lead['TrafficEndpoint']]) ? $endpoints[$lead['TrafficEndpoint']] : '';

                        $data[] = [/*$id,*/$broker_lead_id, $timestamp, $integration, $country, $email, $status, $endpoint];
                    } catch (\Exception $ex) {
                    }
                }

                $writer->writeSheet($data, $broker_name, $header);
            }
        } else {
            $writer->writeSheet([], $broker_name, $header);
        }
        return $writer->writeToString();
    }

    private function get_broker_names()
    {
        $where = []; //'partner_type' => '1'];
        $mongo = new MongoDBObjects('partner', $where);
        $partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1, 'partner_name' => 1]]);
        $result = [];
        foreach ($partners as $partner) {
            $id = (array)$partner['_id'];
            $id = $id['oid'];
            $result[$id] = GeneralHelper::broker_name($partner);
        }
        return $result;
    }

    private function get_broker_integrations_names()
    {
        $mongo = new MongoDBObjects('broker_integrations', []);
        $find = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1, 'name' => 1]]);
        $integrations = [];
        foreach ($find as $integration) {
            $integrations[MongoDBObjects::get_id($integration)] = GeneralHelper::broker_integration_name($integration);// $integration['name'] ?? 'Unknown';
        }
        return $integrations;
    }

    private function get_endpoints_names()
    {
        $mongo = new MongoDBObjects('TrafficEndpoints', []);
        $find = $mongo->findMany();
        $endpoints = [];
        foreach ($find as $endpoint) {
            $endpoints[MongoDBObjects::get_id($endpoint)] = $endpoint['token'] ?? 'Unknown';
        }
        return $endpoints;
    }
}
