<?php

namespace App\Repository\Brokers;

use App\Models\Broker;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;

use App\Models\Brokers\BrokerCaps;
use App\Repository\BaseRepository;
use App\Classes\Brokers\ManageCaps;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Models\Brokers\BrokerIntegration;
use App\Repository\Brokers\IBrokerSpyToolRepository;

class BrokerSpyToolRepository extends BaseRepository implements IBrokerSpyToolRepository
{
    /**
     * @var Model
     */

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct()
    {
    }

    private function getAnalizedLead($lead_id, $integration_id)
    {
        // return json_decode('{"success":true,"data_log":{"response":"{\"data\":{\"signupRequestID\":\"L1q5VomYxQ3GWAZrBdl69XJVpEo0X0DyMPn2gzJKbaepkO74R\",\"traderHitID\":25872763,\"broker\":{\"hash\":\"XXX\",\"name\":\"Broker\",\"logo\":\"\\\/public\\\/assets\\\/images\\\/broker-logo.png\",\"logoWhite\":\"\\\/public\\\/assets\\\/images\\\/broker-logo.png\",\"logoBlack\":\"\\\/public\\\/assets\\\/images\\\/broker-logo.png\",\"custom1\":null,\"custom2\":null,\"custom3\":null,\"custom4\":null,\"custom5\":null},\"customerID\":\"83e45bc142ff1ac67e795408cc65a802\",\"apiUrl\":null,\"selfDeposit\":false,\"redirect\":{\"url\":\"https:\\\/\\\/cmasterstrk.com\\\/api\\\/v1\\\/brokers\\\/login\\\/redirect.php?signupID=L1q5VomYxQ3GWAZrBdl69XJVpEo0X0DyMPn2gzJKbaepkO74R\",\"method\":\"GET\",\"params\":{\"signup_request_id\":\"L1q5VomYxQ3GWAZrBdl69XJVpEo0X0DyMPn2gzJKbaepkO74R\"}},\"project\":{\"ID\":228,\"hash\":\"Gd\",\"name\":\"CM API\"},\"postbacks\":[]},\"messages\":[\"You have signed up successfully\"],\"date\":\"2022-02-14 12:54:50\",\"executionTime\":13.067858934402466,\"statusCode\":null}","responseCode":200,"executedSeconds":13.233,"integration":{"_id":{"$oid":"6167152b6ac5a177774c9482"},"status":"1","name":"general","apivendor":"5ffc240ea96b0bd7d43d1a07","partnerId":"61671482324e2f132215c632","redirect_url":"","p1":"000E1A7D-8EB6-64BC-A7A3-800228E3D34A","p2":"cmasterstrk.com","p3":null,"p4":null,"last_call":null,"syncJob":true,"cap":"","countries":null,"languages":null,"p10":null,"p5":null,"p6":null,"p7":null,"p8":null,"p9":null,"regulated":false,"last_update":1644843169,"today_leads":1,"total_leads":2395,"today_ftd":0,"today_revenue":0,"total_ftd":179,"total_revenue":188150},"request":{"firstName":"test","lastName":"test","email":"test_1644843290@gmail.com","password":"asd123ASD","phone":"+447951530333","ip":"89.38.69.17","custom1":"TEST","custom2":"TEST","custom3":"","custom4":"","custom5":"","comment":"funnel language: en","offerName":"test.com","offerWebsite":"test.com"},"country_prefix":"44","number_array":"00447951530333","success":true},"redirect_url":"https:\/\/cmasterstrk.com\/api\/v1\/brokers\/login\/redirect.php?signupID=L1q5VomYxQ3GWAZrBdl69XJVpEo0X0DyMPn2gzJKbaepkO74R","redirects":[{"url":"https:\/\/cmasterstrk.com\/api\/v1\/brokers\/login\/redirect.php?signupID=L1q5VomYxQ3GWAZrBdl69XJVpEo0X0DyMPn2gzJKbaepkO74R","ip":"89.38.69.17","user_agent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/98.0.4758.80 Safari\/537.36","servingDomain":"cmasterstrk.com","host":"https:\/\/cmasterstrk.com","http_code":200,"html":"","execution_time_ms":281}]}', true);

        $clientConfig = ClientHelper::clientConfig();
        $url = $clientConfig['serving_domain'] . config('remote.analize_lead_redirects_url_path');

        $post = [
            'lead_id' => $lead_id,
            'integration_id' => $integration_id
        ];

        $headers = [];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 400);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $server_output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $httperror = '';
        if (curl_errno($ch)) $httperror = curl_error($ch);

        if (!empty($httperror)) {
            throw new \Exception($httperror . ', Code:' . $httpcode);
        }

        curl_close($ch);

        // print_r($post);
        // echo $server_output;
        // die();

        if (empty($server_output)) {
            throw new \Exception('Response is empty');
        }

        $data = json_decode($server_output, true);
        if ($data === null) {
            throw new \Exception('Invalid json data');
        }

        return $data;
    }

    private function fetchLead($lead_id)
    {
        $where = array();
        $where['_id'] = new \MongoDB\BSON\ObjectId($lead_id);
        $mongo = new MongoDBObjects('leads', $where);
        $data = $mongo->find();
        return $data;
    }

    private function getBrokers(array &$lead): array
    {
        $brokers = Broker::query()->where('partner_type', '=', '1')
            ->get(['_id', 'partner_name', 'token'])
            ->map(function ($item) use ($lead) {
                return [
                    'value' => $item->_id,
                    'label' => GeneralHelper::broker_name($item),
                    'default' => $item->_id == $lead['brokerId']
                ];
            })
            ->toArray();
        return $brokers;
    }

    private function getIntegrations(array &$lead, array $broker_ids): array
    {
        $integrations = BrokerIntegration::query()
            ->whereIn('partnerId', $broker_ids)
            ->get(['_id', 'partnerId', 'name'])
            ->map(function ($item) use ($lead) {
                return [
                    'brokerId' => $item->partnerId,
                    'value' => $item->_id,
                    'label' => GeneralHelper::broker_integration_name(['partnerId' => $item->partnerId, 'name' => $item->name]),
                    'default' => $item->_id == $lead['integrationId']
                ];
            })
            ->toArray();
        return $integrations;
    }

    private function checkLead(array $payload): array
    {

        $data = ['success' => false];

        try {

            $lead_id = $payload['lead_id'] ?? '';
            $integration_id = $payload['integration_id'] ?? '';

            if (empty($lead_id)) throw new \Exception("Lead is required");

            $analizeData = $this->getAnalizedLead($lead_id, $integration_id);

            $data_log_response = (isset($analizeData['data_log']) &&
                isset($analizeData['data_log']['response']) &&
                is_array($analizeData['data_log']['response']) &&
                count($analizeData['data_log']['response']) > 0
                ?
                print_r(json_decode($analizeData['data_log']['response'], true), true)
                :
                '');

            $data['data'] = $analizeData;
            $data['log_response'] = $data_log_response;

            if (!$analizeData['success']) {
                if (isset($analizeData['message']) && !empty($analizeData['message'])) {
                    throw new \Exception($data['message']);
                } else {
                    throw new \Exception('The broker didn\'t except this lead');
                }
            }

            $data['success'] = true;
        } catch (\Exception $ex) {
            $data['success'] = false;
            $data['error'] = $ex->getMessage();
        } finally {
        }

        return $data;
    }

    public function get_brokers_and_integrations(string $leadId): array
    {
        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            return [
                'success' => false,
                'error' => 'Lead not found'
            ];
        }

        $brokers = $this->getBrokers($lead);
        $broker_ids = array_column($brokers, 'value');
        return [
            'brokers' => $brokers,
            'integrations' => $this->getIntegrations($lead, $broker_ids)
        ];
    }

    public function run(array $payload): array
    {
        return $this->checkLead($payload);
    }
}
