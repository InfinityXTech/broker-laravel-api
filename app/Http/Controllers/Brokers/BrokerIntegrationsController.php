<?php

namespace App\Http\Controllers\Brokers;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerIntegrationRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/integrations"
 * )
 * @OA\Tag(
 *     name="broker_integrations",
 *     description="User related operations"
 * )
 */
class BrokerIntegrationsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerIntegrationRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['api_integrations', 'index', 'get']]);
        // create
        // $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['api_integrations', 'create', 'active', 'update']]);

        // $this->middleware('permissions:brokers[api_integrations]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/integrations/all",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function index(string $brokerId)
    {
        return response()->json($this->repository->index(['*'], ['partnerId' => $brokerId]), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/integrations/active",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function active(string $brokerId)
    {
        return response()->json($this->repository->index(['*'], ['partnerId' => $brokerId, 'status' => 1]), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/integrations/{integrationId}",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="integrationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function get(string $brokerId, string $integrationId)
    {
        return response()->json($this->repository->findById($integrationId, ['*']), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/integrations/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_integrations"},
     *  summary="Create broker integration",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(string $brokerId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2',
            'apivendor' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['partnerId'] = $brokerId;

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
     *  path="/api/broker/{brokerId}/integrations/{integrationId}",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integration",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="integrationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update(string $brokerId, string $integrationId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2',
            'status' => 'required|integer',
            'apivendor' => 'required|string|min:4',
            'p1' => 'nullable|string',
            'p2' => 'nullable|string',
            'p3' => 'nullable|string',
            'p4' => 'nullable|string',
            'p5' => 'nullable|string',
            'p6' => 'nullable|string',
            'p7' => 'nullable|string',
            'p8' => 'nullable|string',
            'p9' => 'nullable|string',
            'p10' => 'nullable|string',
            'languages' => 'nullable|array',
            'redirect_url' => 'nullable|string|min:4',
            'regulated' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        
        $current = $this->repository->findById($integrationId, ['*']);

        if ((int)($current->status) != (int)($payload['status']) && (int)($payload['status']) == 1) {
            $payload['syncJob'] = false;
        }

        $result = $this->repository->update($integrationId, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/broker/{brokerId}/integrations/delete/{integrationId}",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete broker integration",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="integrationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function delete(string $id)
    {
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
