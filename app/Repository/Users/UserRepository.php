<?php

namespace App\Repository\Users;

use App\Models\User;
use App\Helpers\ClientHelper;

use App\Helpers\SystemHelper;
use App\Repository\BaseRepository;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Database\Eloquent\Model;
use App\Repository\Users\IUserRepository;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository implements IUserRepository
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
    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): Collection
    {
        $systemId = SystemHelper::systemId();
        $query = $this->model->with($relations);
        // if ($systemId == 'crm') {
        //     $query = $query->whereRaw([
        //         '$or' => [
        //             ['systemId' => ['$exists' => false]],
        //             ['systemId' => $systemId],
        //         ]
        //     ]);
        // } else {
        //     $query = $query->where('systemId', '=', $systemId);
        // }
        $items = $query->get($columns);
        return $items;
    }

    private function validateUsername($username)
    {
        $mongo = new MongoDBObjects('users', ['username' => $username]);
        $array = $mongo->find();

        if (count($array) >= 1) {
            $new = $username . '_b' . random_int(1, 9);
        } else {
            $new = $username;
        }

        return $new;
    }

    public function getUsernameAndPassword(string $username)
    {
        $ga = new \PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret();

        $clientConfig = ClientHelper::clientConfig();

        $qrCodeUrl = $ga->getQRCodeGoogleUrl(($clientConfig['nickname'] ?? ''), $secret);
        $username = $this->validateUsername($username);
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
        $password = substr(str_shuffle($chars), 0, 8);
        return [$username, $password, $secret, $qrCodeUrl];
    }

    public function create(array $payload): ?Model
    {
        $payload['username'] = $payload['username'] ?? $payload['name'];
        list($username, $password, $secret, $qrCodeUrl) = $this->getUsernameAndPassword($payload['username']);
        $payload['username'] = $username;
        $payload['password'] = $password;
        $payload['qr_secret'] = $secret;
        $payload['qr_img'] = $qrCodeUrl;

        $payload['systemId'] = empty($payload['systemId'] ?? '') ? SystemHelper::systemId() : $payload['systemId'];

        $payload['status'] = (int)($payload['status'] ?? 1);

        $model = User::create($payload);

        // return $model->fresh();
        return $model;
    }

    public function reset_password(string $userId, string $password): string
    {
        if (empty($password)) {
            $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*_";
            $password = substr(str_shuffle($chars), 0, 8);
        }

        $model = User::query()->find($userId);
        $model->password = bcrypt($password);
        $model->save();
        return $password;
    }

    public function update_permissions(string $userId, array $payload): bool
    {
        foreach ($payload['permissions'] as &$permission) {
            $permission['active'] = !empty($permission['access']);
        }
        // print_r($payload['permissions']);
        $model = $this->findById($userId);
        return $model->update($payload);
    }
}
