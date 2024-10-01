<?php

namespace App\Classes\TrafficEndpoints;

use App\Helpers\GeneralHelper;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;
use App\Classes\TrafficEndpoints\CodeWriter;
use App\Helpers\ClientHelper;

class ManageSimulator
{
    private function get_feed_config($id, $country, $language, $group_by_fields)
    {
        $clientConfig = ClientHelper::clientConfig();
        $url = $clientConfig['serving_domain'] . config('remote.feed_simulation_url_path') . '/?gzip';//&base64';

        $post = [
            'ep' => $id,
            'cl' => $country . '_' . $language,
            'gr' => $group_by_fields
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

        if (!empty($server_output)) {
            $server_output = gzuncompress($server_output);//gzdecode($server_output); //gzuncompress
            // $server_output = base64_decode($server_output);
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $httperror = '';
        if (curl_errno($ch)) $httperror = curl_error($ch);
        curl_close($ch);

        if (!empty($httperror)) {
            throw new \Exception($httperror . ', Code:' . $httpcode);
        }

        return json_decode($server_output, true);
    }

    private function get_endpoint_names()
    {
        $where = [];
        $mongo = new MongoDBObjects('TrafficEndpoints', $where);
        $partners = $mongo->findMany();
        $result = [];
        foreach ($partners as $partner) {
            $result[MongoDBObjects::get_id($partner)] = ($partner['token'] ?? '');
        }
        return $result;
    }

    private function get_broker_names()
    {
        $where = ['partner_type' => '1'];
        $mongo = new MongoDBObjects('partner', $where);
        $partners = $mongo->findMany(['projection' => ['_id' => 1, 'token' => 1, 'created_by' => 1, 'account_manager' => 1, 'partner_name' => 1]]);
        $result = [];
        foreach ($partners as $partner) {
            $result[MongoDBObjects::get_id($partner)] = GeneralHelper::broker_name($partner);
        }
        return $result;
    }

    private function get_broker_crs($country_code, $language_code)
    {
        $cl = $country_code . '_' . $language_code;
        $where = ['metric' => 'last_week_cr'];
        $mongo = new MongoDBObjects('metrics', $where);
        $metrics = $mongo->findMany();
        $result = [];
        foreach ($metrics as $metric) {
            $result[$metric['brokerId']] = $metric['value'][$cl]['cr'] ?? 0;
        }
        $where = ['metric' => 'current_week_cr'];
        $mongo = new MongoDBObjects('metrics', $where);
        $metrics = $mongo->findMany();
        foreach ($metrics as $metric) {
            $result[$metric['brokerId']] = ($metric['value'][$cl]['cr'] ?? 0) ?: ($result[$metric['brokerId']] ?? 0);
        }
        return $result;
    }

    private function get_integration_names()
    {
        $where = [];
        $mongo = new MongoDBObjects('broker_integrations', $where);
        $integrations = $mongo->findMany(['projection' => ['_id' => 1, 'partnerId' => 1, 'name' => 1]]);
        $result = [];
        foreach ($integrations as $integration) {
            $result[MongoDBObjects::get_id($integration)] = GeneralHelper::broker_integration_name($integration); //$integration['name'] ?? '';
        }
        return $result;
    }

    public function simulation_graph($endpoint_id, $country_code, $language_code, $group_by_fields)
    {
        $feed_config = $this->get_feed_config($endpoint_id, $country_code, $language_code, $group_by_fields);

        if ($feed_config == null) {
            return null;
        }

        // $clientId = ClientHelper::clientId();

        // $integration_names = Cache::get('broker_integrations_names_' . $clientId);
        // if (!$integration_names) {
            $integration_names = $this->get_integration_names();
            // Cache::put('broker_integrations_names_' . $clientId, $integration_names, 60 * 60);
        // }

        // $broker_names = Cache::get('broker_names_' . $clientId);
        // if (!$broker_names) {
            $broker_names = $this->get_broker_names();
            // Cache::put('broker_names_' . $clientId, $broker_names, 60 * 60);
        // }

        $broker_crs = $this->get_broker_crs($country_code, $language_code);

        $traces = $feed_config['traces'];
        $nodes = $feed_config['nodes'];

        asort($traces);

        $dot = [];
        $dot[] = 'digraph G {';
        $dot[] = 'graph[tooltip=" "]';
        $dot[] = 'node[shape=rect,fontsize=12,tooltip=" "]';
        $dot[] = 'edge[fontsize=12,edgetooltip=" ",labeltooltip=" "]';

        foreach ($traces as $trace => $size) {
            $ref = explode('->', $trace, 2);
            if (count($ref) == 2) {
                $percent = round($size * 100 / $traces[$ref[0]]);
                if ($percent < 100) {
                    $dot[] = $trace . '[label=" ' . $percent . '% "]';
                } else {
                    $dot[] = $trace;
                }
                continue;
            }

            $node = $nodes[$trace];
            if ($node['type'] == 'end_if') {
                $dot[] = $trace . '[shape=point]';
                continue;
            }
            if ($node['type'] == 'select' && empty($node['bucket'])) {
                $dot[] = $trace . '[label="' . $node['text'] . '",color="#dddddd",fontcolor="#dddddd"]';
                continue;
            }
            if ($node['type'] == 'select' && !empty($node['bucket'])) {
                $label = [$node['text']];
                foreach ($node['bucket'] as $bucket) {
                    foreach ($bucket as $integration_id => $int_waterfalls) {
                        foreach ($int_waterfalls as $waterfall) {
                            $skip = $waterfall['skipped'] ? "\u{0336}" : '';
                            $cr = min(999, $broker_crs[$waterfall['broker_id']] ?? 0);
                            $broker = ($broker_names[$waterfall['broker_id']] ?? $waterfall['broker_id']);
                            $integration = ($integration_names[$integration_id] ?? $integration_id);
                            $label[] = $skip . $broker . ' (' . $integration . ') CR:' . $cr . '%'; //html_entity_decode(
                        }
                    }
                }
                $dot[] = $trace . '[label="' . implode("\n", $label) . '"]';
                continue;
            }
            $dot[] = $trace . '[label="' . $node['text'] . '"]';
        }

        $dot[] = '}';
        return implode("\n", $dot);
    }

    private function _update_simulation_code($endpoint_id, $country_code, $language_code, $group_by_fields)
    {
        $feed_config = $this->get_feed_config($endpoint_id, $country_code, $language_code, $group_by_fields);
        if ($feed_config == null) {
            return '<div class="alert alert-danger" role="alert">There is no Broker integration connections at the current time</div>';
        }

        $code = new CodeWriter($this->get_integration_names(), $this->get_broker_names());

        foreach ($feed_config as $line) {
            $code->write($line);
        }

        return $code->output();
    }
}
