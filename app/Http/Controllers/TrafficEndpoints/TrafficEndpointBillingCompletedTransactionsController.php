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
 * path="/api/traffic_endpoint/{trafficEndpointId}/billing/completed_transactions"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing_completed_transactions",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingCompletedTransactionsController extends ApiController
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
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['select']]);

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/completed_transactions/all",
     *  tags={"traffic_endpoint_billing_completed_transactions"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete traffic_endpoint billing/entities",
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
        return response()->json($this->repository->feed_completed_transactions($trafficEndpointId), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/completed_transactions/select{id}",
     *  tags={"traffic_endpoint_billing_completed_transactions"},
     *  summary="Delete traffic_endpoint billing/entities",
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
    public function select(string $trafficEndpointId, string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }
}
