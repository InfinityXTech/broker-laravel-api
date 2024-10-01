<?php

namespace App\Repository\Forms;

use App\Models\Form;

use App\Repository\BaseRepository;
use App\Repository\Forms\IFormRepository;

class FormRepository extends BaseRepository implements IFormRepository
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
    public function __construct(Form $model)
    {
        $this->model = $model;
    }
}