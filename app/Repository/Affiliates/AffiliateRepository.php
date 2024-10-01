<?php

namespace App\Repository\Affiliates;

use Exception;
use App\Models\MLeads;

use GoogleAuthenticator;
use App\Helpers\GeoHelper;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Classes\DataTransformer;
use App\Models\MarketingCampaign;
use Ramsey\Uuid\Nonstandard\Uuid;
use App\Classes\History\HistoryDB;
use App\Models\MarketingAffiliate;
use App\Repository\BaseRepository;
use App\Models\MarketingAdvertiser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Classes\History\HistoryDBAction;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Affiliates\IAffiliateRepository;

class AffiliateRepository extends BaseRepository implements IAffiliateRepository
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
    public function __construct(?MarketingAffiliate $model = null)
    {
        $this->model = $model;
    }

    public function register(array $payload): bool
    {
        $check_email = MarketingAffiliate::query()->where('email_md5', '=', md5($payload['email']))->get(['_id'])->count() > 0;

        if ($check_email) {
            throw new Exception('email_taken');
        }
        if (($payload['telegram'] ?? '') != '') {

            $check_telegram = MarketingAffiliate::query()->where('telegram', '=', $payload['telegram'])->get(['_id'])->count() > 0;
            if ($check_telegram) {
                throw new Exception('telegram_taken');
            }
        }
        if (($payload['skype'] ?? '') != '') {

            $check_skype = MarketingAffiliate::query()->where('skype', '=', $payload['skype'])->get(['_id'])->count() > 0;
            if ($check_skype) {
                throw new Exception('skype_taken');
            }
        }

        if (($payload['whatsapp'] ?? '') != '') {
            $check_whatsapp = MarketingAffiliate::query()->where('whatsapp', '=', $payload['whatsapp'])->get(['_id'])->count() > 0;
            if ($check_whatsapp) {
                throw new Exception('whatsapp_taken');
            }
        }

        if (!empty(($payload['password'] ?? '')) && ($payload['password'] ?? '') != ($payload['confirm_password'] ?? '')) {
            throw new Exception('password_taken');
        }

        if (isset($payload['confirm_password'])) {
            unset($payload['confirm_password']);
        }

        $uuid = Uuid::uuid4()->toString();
        $payload['confirmCode'] = $uuid;
        $payload['email_md5'] = md5($payload['email']);
        $payload['email_confirmed'] = false;

        $model = new MarketingAffiliate();
        $result = $model->save($payload);

        if ($result) {

            $clientConfig = ClientHelper::clientConfig();

            $verifyUrl = url()->current() . '/api/verify/user/' . $uuid;

            $data = [
                'nick_name' => $clientConfig['nickname'],
                'first_name' => $payload['full_name'],
                'token' => $payload['confirmCode'],
                'email' => $payload['email'],
                'approve_email_url' => $verifyUrl
            ];

            Mail::send(['html' => 'emails.affiliate_registration'], $data, function ($message) use ($payload, $clientConfig) {
                $message->to($payload['email'])->subject('Registration (' . $clientConfig['nickname'] . ')');
            });
        }

        return $result;
    }

    public function stat_under_review(): array
    {
        $all = MarketingAffiliate::all(['under_review'])->whereIn("under_review", ['0', '1', '2']);
        $result = ['all' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($all as $rec) {
            $result['all'] += 1;
            if ((int)$rec->under_review == 0) {
                $result['under_review'] += 1;
            }
            if ((int)$rec->under_review == 1) {
                $result['approved'] += 1;
            }
            if ((int)$rec->under_review == 2) {
                $result['rejected'] += 1;
            }
        }
        return $result;
    }

    public function index(array $columns = ['*'], array $relations = [], $where = []): array
    {
        $query = MarketingAffiliate::query()->with($relations);

        if (Gate::allows('affiliates[is_only_assigned=1]')) {
            $user_token = Auth::id();
            $query = $query->where(function ($q) use ($user_token) {
                $q->where('created_by', '=', $user_token)->orWhere('account_manager', '=', $user_token);
            });
        }

        foreach ($where as $where_key => $where_value) {
            if (is_array($where_value)) {
                $query = $query->whereIn($where_key, $where_value);
            } else {
                $query = $query->where($where_key, '=', $where_value);
            }
        }

        if (!isset($where['under_review'])) {
            if (Gate::allows('role:admin')) {
                $query = $query->where(function ($q) {
                    $q->whereIn('under_review', [1, 2])->orWhere('under_review', '=', null);
                });
            } else {
                $query = $query->where(function ($q) {
                    $q->where('under_review', '=', 1)->orWhere('under_review', '=', null);
                });
            }
        }

        $items = $query->get($columns)->toArray();

        foreach ($items as &$item) {

            $item['name'] = ($item['name'] ?? '') . ' (' . ($item['token'] ?? '') . ')';

            if (($item['under_review'] ?? -1) == 0) {

                $ip = $item['ip'] ?? '';
                $country = '';
                if (!empty($ip)) {
                    $geo = [];
                    try {
                        $geo = GeoHelper::getGeoData($ip);
                    } catch (Exception $e) {
                    }
                    $country = $geo['country'] ?? '';
                    if (!empty($country)) {
                        $countries = GeneralHelper::countries(true);
                        if (isset($countries[strtolower(trim($country))])) {
                            $country = $countries[strtolower($country)] . ' (' . $country . ')';
                        }
                    }
                }

                $item['ApplicationJson'] = [
                    'ip' => $ip,
                    'country' => $country,
                    'full_name' => $item['full_name'] ?? '',
                    'timestamp' => !empty($item['created_at']) ? strtotime($item['created_at']) * 1000 : '',
                    'skype' => $item['skype'] ?? '',
                    'telegram' => $item['telegram'] ?? '',
                    'whatsapp' => $item['whatsapp'] ?? '',
                    'email_confirmed' => $item['email_confirmed'] ?? false,
                ];
            }
        }

        return $items;
    }

    private function get_new_token()
    {
        while (true) {
            $token = $this->generate_token();
            $mongo = new MongoDBObjects('marketing_affiliates', ['token' => $token]);
            if ($mongo->count() == 0) {
                return $token;
            }
        }
    }

    private function generate_token()
    {
        $charsz = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $token = 'AF' . substr(str_shuffle($charsz), 0, 2);
        return $token . '' . random_int(1, 100);
    }

    private function getSecretData()
    {
        $account_api_key = md5(random_int(1, 100000000)) . '' . time() . '' . random_int(1, 100000);
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
        $password = substr(str_shuffle($chars), 0, 8);

        $partnerToken = $this->generate_token();

        $ga = new \PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret();

        $clientConfig = ClientHelper::clientConfig();
        $qrCodeUrl = $ga->getQRCodeGoogleUrl(($clientConfig['nickname'] ?? '') . ' (' . $partnerToken . ') ', $secret);

        return [
            'api_key' => $account_api_key,
            'token' => $partnerToken,
            'account_password' => md5($password),
            'login_qr' => $qrCodeUrl,
            'qr_secret' => $secret
        ];
    }

    public function create(array $payload): ?Model
    {
        $count = MarketingAffiliate::query()->where('email_md5', '=', $payload['email_md5'])->get(['_id'])->count();

        if ($count > 0) {
            throw new Exception('This email already exists');
        }

        $data = $this->getSecretData();
        foreach ($data as $key => $value) {
            $payload[$key] = $value;
        }

        $payload['status'] = '3';

        $payload['created_by'] = Auth::id();

        $var = date("Y-m-d H:i:s");
        $payload['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    public function reset_password(string $affiliateId): array
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
        $password = substr(str_shuffle($chars), 0, 15);

        $model = MarketingAffiliate::query()->find($affiliateId);

        $model->aff_dashboard_pass = bcrypt($password);
        $model->save();

        return ['success' => true, 'password' => $password];
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
                throw new \Exception("No data to update");
            }

            $query = MLeads::query()->where('EventType', '!=', 'CLICK')->whereIn('ClickID', $in);
            $items = $query->get(['_id']);
            $count = $items->count();
            $query->update(['AffiliatePayout' => 0]);

            // history
            $fields = ['un_payable'];
            $_data = ['un_payable' => true];
            $category = 'affiliate_un_payable';
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

    public function application_approve(string $affiliateId): bool
    {
        $payload = MarketingAffiliate::query()->find($affiliateId)->toArray();

        $payload['under_review'] = 1;

        $email = $payload['email'];

        // email
        $crypt = new DataTransformer();
        $payload['email_encrypted'] = $crypt->encrypt($email);
        $payload['email_md5'] = md5($email);
        unset($payload['email']);

        $payload['name'] = $payload['full_name'] ?? '';

        $data = $this->getSecretData();
        foreach ($data as $key => $value) {
            $payload[$key] = $value;
        }

        $payload['status'] = '1';

        $payload['created_by'] = Auth::id();

        $var = date("Y-m-d H:i:s");
        $payload['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $model = MarketingAffiliate::findOrFail($affiliateId);

        $result = $model->update($payload);

        if ($result) {
            $clientConfig = ClientHelper::clientConfig();

            $html = '';

            $data = [
                'nick_name' => $clientConfig['nickname'],
                'first_name' => $payload['full_name'],
                'html' => $html
            ];

            Mail::send(['html' => 'emails.affiliate_approved'], $data, function ($message) use ($email, $clientConfig) {
                $message->to($email)->subject('Your registration approved (' . $clientConfig['nickname'] . ')');
            });
        }

        return $result;
    }

    public function application_reject(string $affiliateId): bool
    {

        $payload = MarketingAffiliate::query()->find($affiliateId)->toArray();

        $payload['under_review'] = 2;

        // email
        $crypt = new DataTransformer();
        $payload['email_encrypted'] = $crypt->encrypt($payload['email']);
        $payload['email_md5'] = md5($payload['email']);
        unset($payload['email']);

        $payload['name'] = $payload['full_name'] ?? '';

        $data = $this->getSecretData();
        foreach ($data as $key => $value) {
            $payload[$key] = $value;
        }

        $payload['status'] = '0';

        $payload['created_by'] = Auth::id();

        $var = date("Y-m-d H:i:s");
        $payload['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $model = MarketingAffiliate::findOrFail($affiliateId);

        return $model->update($payload);
    }

    public function draft(string $affiliateId): bool
    {
        $model = MarketingAffiliate::findOrFail($affiliateId);
        $model->status = '3';
        return $model->save();
    }

    public function delete(string $affiliateId): bool
    {
        $model = MarketingAffiliate::findOrFail($affiliateId);
        return $model->delete();
    }

    public function sprav_offers(): array
    {
        $result = [];
        $advertisers = MarketingAdvertiser::query()->whereIn('status', ['1', 1])->get(['_id', 'name', 'token']);
        foreach ($advertisers as $advertiser) {
            $campaigns = MarketingCampaign::query()
                ->where('advertiserId', '=', $advertiser->_id)
                ->whereIn('status', ['1', 1])
                ->get(['_id', 'name', 'token']);
            foreach ($campaigns as $campaign) {
                $result[] = [
                    'value' => $campaign->_id,
                    'label' => $advertiser->name . ' (' . $advertiser->token . ') - ' . $campaign->name . ' (' . $campaign->token . ')',
                ];
            }
        }
        return $result;
    }
}
