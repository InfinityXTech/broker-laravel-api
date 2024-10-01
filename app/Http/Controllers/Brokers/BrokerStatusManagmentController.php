<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerStatusManagmentRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/status_management"
 * )
 * @OA\Tag(
 *     name="broker_status_management",
 *     description="User related operations"
 * )
 */
class BrokerStatusManagmentController extends ApiController
{
    private $repository;

    /**
     * Create a new Controller instance.
     *
     * @return void
     */
    public function __construct(IBrokerStatusManagmentRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:brokers[status_management]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/status_management/all",
     *  tags={"broker_status_management"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all broker statuses",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function index(string $brokerId)
    {
        return response()->json($this->repository->index(['*'], ['broker' => $brokerId]), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/status_management/{status_managementId}",
     *  tags={"broker_status_management"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker statuses",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="status_managementId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function get(string $brokerId, string $id)
    {
        return response()->json($this->repository->findById($id, ['*']), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/status_management/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_status_management"},
     *  summary="Create broker statuses",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(string $brokerId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'broker_status' => 'required|string',
            'status' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['broker'] = $brokerId;

        try {
            $model = $this->repository->create($payload);
            return response()->json($model, 200);
        } catch (\Exception $ex) {
            return response()->json(['broker_status' => [$ex->getMessage()]], 422);
        }

    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/status_management/update/{status_managementId}",
     *  tags={"broker_status_management"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update broker statuses",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="status_managementId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update(string $brokerId, string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'broker_status' => 'required|string',
            'status' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {
            $result = $this->repository->update($id, $payload);
            return response()->json([
                'success' => $result
            ], 200);
        } catch (\Exception $ex) {
            return response()->json(['broker_status' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/broker/{brokerId}/status_management/delete/{status_managementId}",
     *  tags={"broker_status_management"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker statuses",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="status_managementId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function delete(string $brokerId, string $id)
    {
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
