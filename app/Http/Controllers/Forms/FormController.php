<?php

namespace App\Http\Controllers\Forms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Repository\Forms\IFormRepository;

use App\Http\Controllers\ApiController;

class FormController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IFormRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    public function index()
    {
        return response()->json($this->repository->all(), 200);
    }

    public function get(string $id)
    {
        return response()->json($this->repository->findById($id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:4',
            'active' => 'bool',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->create($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:4',
            'active' => 'bool',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->update($id, $payload);

        return response()->json($model, 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(string $id)
    {
        $model = $this->repository->delete($id);

        return response()->json($model, 200);
    }
}
