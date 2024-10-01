<?php

namespace App\Http\Controllers\Affiliates;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Affiliates\IAffiliateBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/affiliates/{affiliateId}/billing/general_balances"
 * )
 * @OA\Tag(
 *     name="affiliates_billing_general_balances",
 *     description="User related operations"
 * )
 */
class AffiliateBillingGeneralBalancesController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAffiliateBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_affiliates[active=1]', []);
        // view
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['feed_billing_general_balances', 'feed_billing_balances_log', 'history_log_billing_general_balances']]);

        // $this->middleware('permissions:marketing_affiliates[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/general_balances/feed",
     *  tags={"affiliates_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function feed_billing_general_balances(string $affiliateId)
    {
        return response()->json($this->repository->feed_billing_general_balances($affiliateId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/general_balances/balances_log",
     *  tags={"affiliates_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function feed_billing_balances_log(string $affiliateId)
    {
        return response()->json($this->repository->feed_billing_balances_log($affiliateId, 1, 60), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/general_balances/balances_log",
     *  tags={"affiliates_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function post_feed_billing_balances_log(Request $request, string $affiliateId)
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

        return response()->json($this->repository->feed_billing_balances_log($affiliateId, $page, $count_in_page), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/general/balance/logs/update/{logId}",
     *  tags={"affiliates_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function update_billing_balances_log(Request $request, string $affiliateId, string $logId)
    {
        $validator = Validator::make($request->all(), [
            'real_balance' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->update_billing_balances_log($affiliateId, $logId, $payload), 200);
    }


    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/crg/logs",
     *  tags={"affiliates_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate billing balance",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function crg_logs(string $affiliateId)
    {
        return response()->json($this->repository->get_crg_logs($affiliateId, ['page' => 1, 'count_in_page' => 60]), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/crg/logs",
     *  tags={"affiliates_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate billing balance",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function post_crg_logs(Request $request, string $affiliateId)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|int',
            'count_in_page' => 'required|int',
            'timeframe' => 'required|string',
            'action_by' => 'nullable|string',
            'country_code' => 'nullable|string',
            'crg' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->get_crg_logs($affiliateId, $payload), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/general_balances/history_log",
     *  tags={"affiliates_billing_general_balances"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function history_log_billing_general_balances(Request $request, string $affiliateId)
    {
        return response()->json($this->repository->history_log_billing_general_balances($affiliateId, $request->all()), 200);
    }
}
