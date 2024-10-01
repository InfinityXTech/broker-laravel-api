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
 * path="/api/traffic_endpoint/{trafficEndpointId}/billing/general_balances"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing_general_balances",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingGeneralBalancesController extends ApiController
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
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['feed_billing_general_balances', 'feed_billing_balances_log', 'history_log_billing_general_balances']]);

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/general_balances/feed",
     *  tags={"traffic_endpoint_billing_general_balances"},
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
    public function feed_billing_general_balances(string $trafficEndpointId)
    {
        return response()->json($this->repository->feed_billing_general_balances($trafficEndpointId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/general_balances/balances_log",
     *  tags={"traffic_endpoint_billing_general_balances"},
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
    public function feed_billing_balances_log(string $trafficEndpointId)
    {
        return response()->json($this->repository->feed_billing_balances_log($trafficEndpointId, 1, 60), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/general_balances/balances_log",
     *  tags={"traffic_endpoint_billing_general_balances"},
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
    public function post_feed_billing_balances_log(Request $request, string $trafficEndpointId)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|int',
            'count_in_page' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $page = $payload['page'] ?? 1;
        $count_in_page = $payload['count_in_page'] ?? 20;

        return response()->json($this->repository->feed_billing_balances_log($trafficEndpointId, $page, $count_in_page), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/general/balance/logs/update/{logId}",
     *  tags={"traffic_endpoint_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="logId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update_billing_balances_log(Request $request, string $trafficEndpointId, string $logId)
    {
        $validator = Validator::make($request->all(), [
            'real_balance' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->update_billing_balances_log($trafficEndpointId, $logId, $payload), 200);
    }


    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/recalculate/logs",
     *  tags={"traffic_endpoint_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get endpoint billing balance",
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
    public function crg_recalculate_logs(string $trafficEndpointId)
    {
        return response()->json($this->repository->get_recalculate_logs($trafficEndpointId, ['page' => 1, 'count_in_page' => 60]), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/recalculate/logs",
     *  tags={"traffic_endpoint_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get endpoint billing balance",
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
    public function post_recalculate_logs(Request $request, string $trafficEndpointId)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|int',
            'count_in_page' => 'required|int',
            'timeframe' => 'required|string',
            'action_by' => 'nullable|string',
            'country_code' => 'nullable|string',
            'scheme_type' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->get_recalculate_logs($trafficEndpointId, $payload), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/general_balances/history_log",
     *  tags={"traffic_endpoint_billing_general_balances"},
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
    public function history_log_billing_general_balances(Request $request, string $trafficEndpointId)
    {
        return response()->json($this->repository->history_log_billing_general_balances($trafficEndpointId, $request->all()), 200);
    }
}
