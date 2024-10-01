<?php

namespace App\Repository\Advertisers;

use Exception;
use App\Models\MLeads;

use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;
use App\Classes\History\HistoryDB;
use App\Repository\BaseRepository;
use App\Models\MarketingAdvertiser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Classes\History\HistoryDBAction;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Advertisers\IAdvertisersRepository;

class AdvertisersRepository extends BaseRepository implements IAdvertisersRepository
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
    public function __construct(MarketingAdvertiser $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): Collection
    {
        $query = $this->model->with($relations);
        if (Gate::allows('advertisers[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $query = $query->where(function ($q) use ($user_token) {
                $q->where('created_by', '=', $user_token)->orWhere('account_manager', '=', $user_token);
            });
        }

        $items = $query->get($columns);

        // foreach ($items as &$item) {
        // }

        return $items;
    }

    private function get_new_token()
    {
        while (true) {
            $token = $this->generate_token();
            $mongo = new MongoDBObjects('marketing_advertisers', ['token' => $token]);
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

        $payload['created_by'] = Auth::id();

        $payload['action_on_negative_balance'] = 'stop';

        $var = date("Y-m-d H:i:s");
        $payload['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    public function un_payable(array $payload): array
    {
        $data = ['success' => false];
        try {

            $ids_str = $payload['clickIds'] ?? '';
            $ids_str = str_replace(",", PHP_EOL, $ids_str);

            $ids = preg_split("/(\r\n|\n|\r)/", $ids_str);
            $in = [];

            $errors = '';
            foreach ($ids as $id) {
                if (!empty($id)) {
                    try {
                        // $in[] = new \MongoDB\BSON\ObjectId(trim($id));
                        $in[] = trim($id);
                    } catch (\Exception $ex) {
                        $errors .= '<br/>' . $id . ' is not valid ID';
                    }
                }
            }

            if (!empty($errors)) {
                throw new \Exception('Operation aborted. Fix leads with errors: ' . $errors);
            }

            if (count($in) == 0) {
                throw new Exception("No data to update");
            }
            $query = MLeads::query()->where('EventType', '!=', 'CLICK')->whereIn('ClickID', $in);
            $items = $query->get(['ClickID']);
            $count = $query->get(['ClickID'])->count();
            $query->update([
                'AdvertiserPayout' => 0
            ]);

            // history
            $fields = ['un_payable'];
            $_data = ['un_payable' => true];
            $category = 'advertiser_un_payable';
            $reasons_change_crg = ($payload['reason_change2'] ?? $payload['reason_change'] ?? '');
            foreach ($items as $item) {
                HistoryDB::add(HistoryDBAction::Update, 'mleads', $_data, (string)$item['_id'], '', $fields, $category, $reasons_change_crg);
            }

            $data = ['success' => true, 'count' => $count];
        } catch (\Exception $ex) {
            $data = ['success' => false, 'error' => $ex->getMessage()];
        }
        return $data;
    }

    public function draft(string $advertiserId): bool
    {
        $model = MarketingAdvertiser::findOrFail($advertiserId);
        $model->status = '3';
        return $model->save();
    }

    public function delete(string $advertiserId): bool
    {
        $model = MarketingAdvertiser::findOrFail($advertiserId);
        return $model->delete();
    }
}
