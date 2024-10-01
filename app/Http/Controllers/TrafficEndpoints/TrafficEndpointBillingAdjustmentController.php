<?php

namespace App\Http\Controllers\TrafficEndpoints;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\TrafficEndpoints\ITrafficEndpointBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{traffic_endpointId}/billing/adjustmentss"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing_adjustments",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingAdjustmentController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
        $this->middleware('permissions:traffic_endpoint[active=1]', []);

        // view
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{traffic_endpointId}/billing/adjustments/all",
     *  tags={"traffic_endpoint_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic_endpoints billing/adjustment",
     *       @OA\Parameter(
     *          name="traffic_endpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $traffic_endpointId)
    {
        return response()->json($this->repository->feed_adjustments($traffic_endpointId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{traffic_endpointId}/billing/adjustments/{id}",
     *  tags={"traffic_endpoint_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic_endpoint billing/adjustment",
     *       @OA\Parameter(
     *          name="traffic_endpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get(string $traffic_endpointId, string $id)
    {
        return response()->json($this->repository->get_adjustment($traffic_endpointId, $id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{traffic_endpointId}/billing/adjustments/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_billing_adjustments"},
     *  summary="Create traffic_endpoint billing/adjustment",
     *       @OA\Parameter(
     *          name="traffic_endpointId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $traffic_endpointId)
    {
        $validator = Validator::make($request->all(), [
            'amount_sign' => 'required|integer',
            'amount' => 'required|integer',
            'description' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
            'bi' => 'bool|nullable',
            'bi_timestamp' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['endpoint'] = $traffic_endpointId;

        $model = $this->repository->create_adjustment($traffic_endpointId, $payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{traffic_endpointId}/billing/adjustments/update/{traffic_endpointId}",
     *  tags={"traffic_endpoint_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint billing/adjustment",
     *       @OA\Parameter(
     *          name="traffic_endpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $traffic_endpointId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'amount_sign' => 'required|integer',
            'amount' => 'required|integer',
            'description' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
            'bi' => 'bool|nullable',
            'bi_timestamp' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update_adjustment($traffic_endpointId, $id, $payload);

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
     *  path="/api/{traffic_endpointId}/billing/adjustments/delete/{traffic_endpointId}",
     *  tags={"traffic_endpoint_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint billing/adjustment",
     *       @OA\Parameter(
     *          name="traffic_endpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function delete(string $traffic_endpointId, string $id)
    {
        $result = $this->repository->delete_adjustment($traffic_endpointId, $id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
