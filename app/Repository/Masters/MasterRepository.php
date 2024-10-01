<?php

namespace App\Repository\Masters;

use App\Models\Master;
use App\Helpers\ClientHelper;

use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use App\Models\Masters\MasterIntegration;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Masters\IMasterRepository;

class MasterRepository extends BaseRepository implements IMasterRepository
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
    public function __construct(Master $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): Collection
    {
        $query = $this->model->with($relations);
        if (Gate::allows('masters[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $query = $query->where(function ($q) use ($user_token) {
                $q->where('user_id', '=', $user_token)->orWhere('account_manager', '=', $user_token);
            });
        }
        $items = $query->get($columns);

        if (in_array('cr', $columns)) {
            foreach ($items as &$item) {
                $cr = 0;
                $total_ftd = (int)(isset($partner['total_ftd']) ? $partner['total_ftd'] : 0);
                $total_leads = (int)(isset($partner['total_leads']) ? $partner['total_leads'] : 0);
                if ($total_ftd > 0) {
                    $cr = ($total_ftd / $total_leads) * 100;
                }
                $item->cr = $cr;
            }
        }

        return $items;
    }

    private function validateUsername($username)
    {
        $is_master_exists = $this->model->select(['account_username'])->find(['account_username' => $username])->count();
        if ($is_master_exists) {
            $new = $username . '_b' . random_int(1, 9);
        } else {
            $new = $username;
        }

        return $new;
    }

    private function get_partner_password()
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
        $password = substr(str_shuffle($chars), 0, 8);
        return [md5($password), $password];
    }

    private function get_partner_token()
    {
        $charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $token = substr(str_shuffle($charsz), 0, 2);

        $partnerToken = $token . '' . random_int(1, 100);
        return $partnerToken;
    }

    public function create(array $payload): ?Model
    {
        $password = $this->get_partner_password()[0];
        $partnerToken = $this->get_partner_token();

        $ga = new \PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret();

        $clientConfig = ClientHelper::clientConfig();

        $qrCodeUrl = $ga->getQRCodeGoogleUrl(($clientConfig['nickname'] ?? '') . ' (' . $partnerToken . ') ', $secret);

        $payload['password'] = $password;
        $payload['token'] = $partnerToken;
        $payload['created_by'] = Auth::id();
        $payload['qr_code'] = $qrCodeUrl;
        $payload['qr_secret'] = $secret;
        $payload['status'] = 1;
        $payload['master_status'] = 1;
        $payload['type_of_calculation'] = 'percentage_profit';
        $payload['calculation_price'] = 1;
        $payload['total_cost'] = 0;

        $payload['today_revenue'] = 0;
        $payload['total_revenue'] = 0;
        $payload['today_deposits'] = 0;
        $payload['total_deposits'] = 0;
        $payload['today_cost'] = 0;
        $payload['total_cost'] = 0;

        $var = date("Y-m-d H:i:s"); // . ' 00:00:00';
        $payload['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    public function reset_password(string $masterId): array
    {
        $model = Master::query()->find($masterId);
        list($hash_password, $password) = $this->get_partner_password();
        $model->password = $hash_password;
        $model->save();
        return ['success' => true, 'password' => $password];
    }
}
