<?php

namespace App\Repository\TagManagement;

use App\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Models\TagManagement;

class TagManagementRepository extends BaseRepository implements ITagManagementRepository
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
    public function __construct(TagManagement $model = null)
    {
        $this->model = $model;
    }

    public function index(array $columns = ['*'], array $relations = []): Collection
    {
        $query = $this->model->with($relations);
        return $query->get($columns);
    }
}
