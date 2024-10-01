<?php

namespace App\Http\Controllers\TagManagement;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\ApiController;
use App\Repository\TagManagement\ITagManagementRepository;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/tag_management"
 * )
 * @OA\Tag(
 *     name="Tag management",
 *     description="Tag management related operations"
 * )
 */
class TagManagementController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITagManagementRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
        // $this->middleware('roles:admin', []);

    }

    /**
     * @OA\Get(
     *  path="/api/tag_management/all",
     *  tags={"tag_management"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all tags",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        $columns = [];
        return response()->json($this->repository->index($columns), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/tag_management/{userId}",
     *  tags={"tag_management"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get tag_management",
     *       @OA\Parameter(
     *          name="userId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get(string $id)
    {
        return response()->json($this->repository->findById($id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/tag_management/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"tag_management"},
     *  summary="Create tag_management",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer',
            'name' => 'required|string',
            'permission' => 'required|string',
            'color' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->create($payload + ['created_by' => auth()->id()]);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/tag_management/update/{userId}",
     *  tags={"tag_management"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put tag_management",
     *       @OA\Parameter(
     *          name="userId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer',
            'name' => 'required|string',
            'permission' => 'required|string',
            'color' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
