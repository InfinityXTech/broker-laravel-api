<?php

namespace App\Repository\MarketingSuite;

use App\Models\Offer;
use App\Scopes\ClientScope;

use App\Helpers\StorageHelper;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\MarketingSuite\IMarketingSuiteRepository;

class MarketingSuiteRepository extends BaseRepository implements IMarketingSuiteRepository
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(Offer $model)
    {
        $this->model = $model;
    }

    private function get_tracking_links($offer, $languages)
    {
        $result = [];
        $domain = $this->get_tracking_url();
        $scheme = parse_url($domain);
        foreach ($languages as $language) {
            $link = '/click';
            $args = $this->makeUrl([
                'token' => strtolower($offer->token),
                'language' => $language
            ]);
            if ($scheme === false || empty($domain)) {
                $url = $link . '?' . $args;
            } else {
                $url = $scheme["scheme"] . "://" . $scheme["host"] . $link . '?' . $args;
            }
            $result[$language] = $url;
        }
        return $result;
    }

    public function index(array $columns = ['*'], array $relations = []): Collection
    {
        // $result = $this->model->with($relations)->get($columns);
        $result = Offer::withoutGlobalScope(new ClientScope)->with($relations)->get($columns);
        $is_market_suit_dashboard_links = Gate::allows('custom:traffic_endpoint[market_suit_dashboard_links]');
        foreach ($result as &$offer) {
            if ($is_market_suit_dashboard_links) {

                $languages = [];
                if (isset($offer->languages)) {
                    $languages = (array)$offer->languages;
                }

                $urls = $this->get_tracking_links($offer, $languages);
                $tracking_links = [];
                foreach ($languages as $language) {
                    $url = $urls[$language];
                    $tracking_links[] = ['language' => $language, 'url' => $url];
                }

                $offer->tracking_links = $tracking_links;
            }
        }
        return $result;
    }

    public function get(string $modelId): ?Model
    {
        $item = Offer::withoutGlobalScope(new ClientScope)->findOrFail($modelId);
        StorageHelper::injectFile('offer', $item, 'logo_image');
        StorageHelper::injectFile('offer', $item, 'screenshot_image');
        return $item;
    }

    private function new_token()
    {
        $charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $token = substr(str_shuffle($charsz), 0, 3);
        $token = $token . '' . rand(10000, 99999);
        return $token;
    }

    public function create(array $payload): ?Model
    {
        $var = date("Y-m-d H:i:s"); // . ' 00:00:00';
        $payload['created_on'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
        $payload['created_by'] = Auth::id();
        $payload['status'] = "1";
        $payload['vertical'] = 'crypto_trading';
        $payload['promoted_offer'] = "0";
        $payload['exclusive_offer'] = "0";
        $payload['token'] = $this->new_token();
        $model = $this->model->create($payload);
        return $model->fresh();
    }

    public function update(string $modelId, array $payload): bool
    {
        $model = Offer::findOrFail($modelId);
        StorageHelper::syncFiles('offer', $model, $payload, 'logo_image', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        StorageHelper::syncFiles('offer', $model, $payload, 'screenshot_image', ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    private function get_tracking_url()
    {
        $mongo = new MongoDBObjects('PlatformAccounts', []);
        $data = $mongo->find();

        $ar = (array)($data['_id'] ?? []);
        $id = $ar['oid'] ?? '';

        if (!empty($id)) {
            $mongo = new MongoDBObjects('PlatformAccounts', array('_id' => new \MongoDB\BSON\ObjectId($id)));
            $data = $mongo->find();
            return $data['marketing_suite_tracking_url'];
        }
        return '';
    }

    private function makeUrl($param_values)
    {
        $params = [
            'token' => '',
            'tp_point' => '',
            'language' => '{language}',
            'clickid' => '{clickid}',
            'subpublisher' => '{subpublisher}',
            'creativeid' => '{creativeid}',
            'smlink_id' => '{smlink_id}',
            'd1' => '{d1}',
            'd2' => '{d2}',
            'd3' => '{d3}',
            'd4' => '{d4}',
            'd5' => '{d5}',
            'd6' => '{d6}',
            'd7' => '{d7}',
            'd8' => '{d8}',
            'd9' => '{d9}',
            'd10' => '{d10}'
        ];

        foreach ($param_values as $param_key => $param_value) {
            $params[$param_key] = $param_value;
        }

        $result = http_build_query($params);
        $result = str_replace('%7B', '{', $result);
        $result = str_replace('%7D', '}', $result);
        return $result;
    }

    public function get_tracking_link(string $modelId): string
    {

        $where = [];
        $where['_id'] = new \MongoDB\BSON\ObjectId($modelId);

        $mongo = new MongoDBObjects('offers', $where);
        $mongo->without_client_id();
        $offer = $mongo->find();

        $domain = $this->get_tracking_url();
        $scheme = parse_url($domain);

        $link = '/click';
        $args = $this->makeUrl([
            'token' => strtolower($offer['token'] ?? '')
        ]);
        if ($scheme === false || empty($domain)) {
            $url = $link . '?' . $args;
        } else {
            $url = $scheme["scheme"] . "://" . $scheme["host"] . $link . '?' . $args;
        }
        return $url;
    }
}
