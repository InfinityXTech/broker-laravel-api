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
 * path="/api/traffic_endpoint/{trafficEndpointId}/billing/chargeback"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing_chargeback",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingChargebackController extends ApiController
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

        // active
        $this->middleware('permissions:traffic_endpoint[active=1]', []);
        // view
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_payment_requests']]);
        // create
        // $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/chargeback/all",
     *  tags={"traffic_endpoint_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic_endpoints billing/chargeback",
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
    public function index(string $trafficEndpointId)
    {
        return response()->json($this->repository->feed_chargebacks($trafficEndpointId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/chargeback/{id}",
     *  tags={"traffic_endpoint_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic_endpoint billing/chargeback",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function get(string $trafficEndpointId, string $id)
    {
        return response()->json($this->repository->get_chargebacks($trafficEndpointId, $id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/chargeback/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_billing_chargeback"},
     *  summary="Create traffic_endpoint billing/chargeback",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $trafficEndpointId)
    {
        $validator = [
            'amount' => 'required|integer',
            'payment_method' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
            'screenshots' => 'required|array',
        ];
        if (!empty($request->input('payment_request'))) {
            unset($validator['amount']);
        }
        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $payload = $validator->validated();
        $payload['endpoint'] = $trafficEndpointId;

        return response()->json($this->repository->create_chargebacks($trafficEndpointId, $payload), 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/chargeback/update/{id}",
     *  tags={"traffic_endpoint_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint billing/chargeback",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function update(Request $request, string $trafficEndpointId, string $id)
    {
        $validator = [
            'amount' => 'required|integer',
            'payment_method' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
            'screenshots' => 'required|array',
        ];
        if (!empty($request->input('payment_request'))) {
            unset($validator['amount']);
        }
        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->create_chargebacks($trafficEndpointId, $payload), 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/{trafficEndpointId}/billing/chargeback/delete/{id}",
     *  tags={"traffic_endpoint_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint billing/chargeback",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
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
    public function delete(string $trafficEndpointId, string $id)
    {
        return response()->json($this->repository->delete_chargebacks($trafficEndpointId, $id), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/chargeback/sprav/payment_requests",
     *  tags={"traffic_endpoint_billing_"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
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
    public function get_payment_requests(string $trafficEndpointId)
    {
        return $this->repository->get_payment_requests_for_chargeback($trafficEndpointId);
    }
}
