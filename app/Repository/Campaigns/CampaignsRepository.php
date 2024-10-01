<?php

namespace App\Repository\Campaigns;

use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;

use App\Models\MarketingCampaign;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Campaigns\ICampaignsRepository;

class CampaignsRepository extends BaseRepository implements ICampaignsRepository
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
    public function __construct(MarketingCampaign $model)
    {
        $this->model = $model;
    }

    public function index(string $advertiserId, array $columns = ['*'], array $relations = []): array
    {
        $query = $this->model->with($relations)->where('advertiserId', '=', $advertiserId);
        if (Gate::allows('campaigns[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $query = $query->where(function ($q) use ($user_token) {
                $q->where('created_by', '=', $user_token)->orWhere('account_manager', '=', $user_token);
            });
        }

        $items = $query->get($columns)->toArray();

        foreach ($items as &$item) {
            $item['targeting_value'] = [];
            $item['country_value'] = [];
            
            if (($item['world_wide_targeting'] ?? false) == true) {
                $world_wide = [
                    '_id' => 'world_wide_targeting',
                    'name' => 'World Wide'
                ];
                $item['country_value'][] = $world_wide;
                $item['targeting_value'][] = $world_wide;
            } else
            foreach($item['targeting_data'] ?? [] as $targeting_data) {
                $item['targeting_value'][] = [
                    '_id' => ($targeting_data['country_code'] ?? '') . '_' . (implode('_', $targeting_data['region_codes'] ?? [])),
                    'name' => str_replace(' ()', '', ($targeting_data['country_name'] ?? '') . ' (' . (implode(', ', array_map(fn($i) => $i['name'], $targeting_data['region_name'] ?? [])) . ')' ))
                ];
                $item['country_value'][] = [
                    '_id' => $targeting_data['country_code'] ?? '',
                    'name' => $targeting_data['country_name'] ?? ''
                ];
            }
        }

        return $items;
    }

    private function get_new_token()
    {
        while (true) {
            $token = $this->generate_token();
            $mongo = new MongoDBObjects('marketing_campaigns', ['token' => $token]);
            if ($mongo->count() == 0) {
                return $token;
            }
        }
    }

    private function generate_token()
    {
        $charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $token = 'CA' . substr(str_shuffle($charsz), 0, 2);
        return $token . '' . random_int(1, 100);
    }

    public function create(array $payload): ?Model
    {
        $payload['token'] = $this->get_new_token();

        $payload['status'] = '3';

        $payload['advertiser_general_payout'] = 0;
        $payload['affiliate_general_payout'] = 0;

        $payload['advertiser_payout'] = 0;

        $payload['created_by'] = Auth::id();

        $var = date("Y-m-d H:i:s");
        $payload['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    public function update(string $campaignId, array $payload): bool {
        $model = MarketingCampaign::findOrFail($campaignId);
        StorageHelper::syncFiles('marketing_offer', $model, $payload, 'screenshot_image', ['jpg', 'jpeg', 'png', 'gif']);
        return $model->update($payload);
    }

    public function draft(string $campoaignId): bool
    {
        $model = MarketingCampaign::findOrFail($campoaignId);
        $model->status = '3';
        return $model->save();
    }
}
