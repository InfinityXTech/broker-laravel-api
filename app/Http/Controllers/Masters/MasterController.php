<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master"
 * )
 * @OA\Tag(
 *     name="master",
 *     description="User related operations"
 * )
 */
class MasterController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMasterRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:masters[active=1]', []);
        // view
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        $this->middleware('permissions:masters[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['update', 'archive', 'reset_password']]);

        $this->middleware('permissions:masters[general]', ['only' => ['update', 'archive', 'reset_password']]);
    }

    /**
     * @OA\Get(
     *  path="/api/master/all",
     *  tags={"master"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all masters",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        $baseColumns = [
            "_id",
            "status",
            "token",
            "type",
            "type_name",
            "today_leads",
            "total_leads",
            "today_revenue",
            "total_revenue",
            "today_ftd",
            "total_ftd",
            "timestamp",
            "assignedto",
            "created_by",
            "nickname"
        ];

        $relations = [
            "assignedto_user:name,account_email",
            "created_by_user:name,account_email"
        ];

        return response()->json($this->repository->index($baseColumns, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}",
     *  tags={"master"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master",
     *       @OA\Parameter(
     *          name="masterId",
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
     *  path="/api/master/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"master"},
     *  summary="Create master",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignedto' => 'required|string|min:6',
            'type' => 'required|string|min:1'
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
    /**
     * @OA\Put(
     *  path="/api/master/update/{masterId}",
     *  tags={"master"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master",
     *       @OA\Parameter(
     *          name="masterId",
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
            'status' => 'integer|nullable',
            'fixed_price_cpl' => 'integer|nullable|between:0,10',
            'master_status' => 'integer|nullable',
            'type_of_calculation' => 'string|nullable',
            'calculation_price' => 'integer|nullable',
            'nickname' => 'string|nullable'
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

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Patch(
     *  path="/api/master/archive/{masterId}",
     *  tags={"master"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function archive(string $id)
    {
        $result = $this->repository->archive($id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Patch(
     *  path="/api/master/reset_password/{masterId}",
     *  tags={"master"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function reset_password(string $id)
    {
        $result = $this->repository->reset_password($id);

        return response()->json($result, 200);
    }
}
