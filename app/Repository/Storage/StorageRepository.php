<?php

namespace App\Repository\Storage;

use App\Models\Offer;
use App\Models\Storage;
use App\Models\StorageModel;

use App\Repository\BaseRepository;
use App\Classes\Storages\ManageFeed;
use App\Classes\StorageWrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Collection;
use App\Repository\Storage\IStorageRepository;

class StorageRepository extends BaseRepository implements IStorageRepository
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
    public function __construct(StorageModel $model)
    {
        $this->model = $model;
    }

    public function info(string $fileId): array {
        return StorageWrapper::get_file($fileId);
    }

    public function content(string $fileId): string {
        return StorageWrapper::get_content($fileId);
    }
}
