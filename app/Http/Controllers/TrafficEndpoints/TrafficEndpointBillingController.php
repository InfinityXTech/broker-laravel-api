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
 * path="/api/traffic_endpoint/{trafficEndpointId}/billing"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingController extends ApiController
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
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['get_payment_methods']]);

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/sprav/payment_methods",
     *  tags={"traffic_endpoint_billing"},
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
    public function get_payment_methods(string $trafficEndpointId)
    {
        return $this->repository->get_payment_methods($trafficEndpointId);
    }

    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/general/manual_status",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set endpoint billing credit_amount",
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
    public function manual_status(Request $request, string $trafficEndpointId)
    {
        $validator = Validator::make($request->all(), [
            'billing_manual_status' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_manual_status($trafficEndpointId, $payload['billing_manual_status']);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
