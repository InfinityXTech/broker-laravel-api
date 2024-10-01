<?php

namespace App\Repository\Clients;

use App\Models\Client;
use App\Scopes\ClientScope;

use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;
use App\Repository\BaseRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use App\Repository\Clients\IClientRepository;
use Exception;

class ClientRepository extends BaseRepository implements IClientRepository
{
    /**
     * @var Model
     */
    protected $model;

    private $file_fields = [
        "login_background_file",
        "logo_big_file",
        "logo_small_file",
        "favicon_file",
        "partner_login_background_file",
        "partner_logo_big_file",
        "partner_logo_small_file",
        "partner_favicon_file",
        "master_login_background_file",
        "master_logo_big_file",
        "master_logo_small_file",
        "master_favicon_file",
    ];

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(Client $model)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): Collection
    {
        $query = $this->model->with($relations)->withoutGlobalScope(new ClientScope);
        return $query->get($columns);
    }

    public function get(
        string $modelId,
        array $columns = ['*'],
        array $relations = [],
        array $appends = []
    ): ?Model {
        $model = $this->model->withoutGlobalScope(new ClientScope)->select($columns)->with($relations)->findOrFail($modelId)->append($appends);

        foreach ($this->file_fields as $field) {
            $file_path = 'clients_assets/' . ClientHelper::clientId() . '/' . $field;
            StorageHelper::injectFiles($file_path, $model, $field);
        }

        return $model;
        // return Client::withoutGlobalScope(new ClientScope)->where('_id', '=', $modelId)->with($relations)->get($columns)->first();
    }

    public function create(array $payload): ?Model
    {
        $payload['status'] = '1';

        $payload['created_by'] = Auth::id();

        $var = date("Y-m-d H:i:s");
        $payload['created_at'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

        $this->model->withoutGlobalScope(new ClientScope);

        $model = $this->model->create($payload);

        return $model->fresh();
    }

    private function get_rand_file_name(string $file_name): string
    {
        $extension_of_file_here = pathinfo($file_name, PATHINFO_EXTENSION);
        $charsz = "abcdefghijklmnopqrstuvwxyz";
        $token = substr(str_shuffle($charsz), 0, 2);
        $new_name = $token . '' . random_int(100000, 999999) . time() . '.' . $extension_of_file_here;
        return $new_name;
    }

    public function update(string $modelId, array $payload): bool
    {
        $model = $this->model->withoutGlobalScope(new ClientScope)->findOrFail($modelId);

        foreach ($this->file_fields as $field) {
            if (isset($payload[$field])) {
                $file = array_slice($payload[$field], 0, count($payload[$field]));
                if (!empty($file)) {
                    $file = $file[0];
                }
                StorageHelper::syncFiles('clients_assets', $model, $payload, $field, ['png', 'gif', 'jpg', 'jpeg', 'ico']);
                if (!is_string($file) && isset($payload[$field][0])) {
                    $file_name = $payload[$field][0] . '.' . $file->getClientOriginalExtension();
                    $path = 'clients_assets/' . ClientHelper::clientId() . '/' . $field;
                    $storage = Storage::disk('local');
                    $storage->deleteDirectory($path);
                    $content = file_get_contents($file->getRealPath());
                    $storage->put($path . '/' . $file_name, $content);
                }
            }
        }

        return $model->update($payload);
    }
}
