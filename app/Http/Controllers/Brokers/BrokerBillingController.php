<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerBillingRepository;
use App\Http\Controllers\ApiController;
use App\Rules\IntegerRanges;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/billing"
 * )
 * @OA\Tag(
 *     name="broker_billing",
 *     description="User related operations"
 * )
 */
class BrokerBillingController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['general_balance', 'general_balance_logs', 'logs']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['settings_negative_balance', 'settings_credit_amount']]);

        $this->middleware('permissions:brokers[is_billing]', []);
    }

    /******* GENERAL *******/

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/general/general_balance",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function general_balance(string $brokerId)
    {
        return response()->json($this->repository->get_general_balance($brokerId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/general/balance/logs",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function general_balance_logs(string $brokerId)
    {
        return response()->json($this->repository->get_general_balance_logs($brokerId, 1, 60), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/billing/general/balance/logs",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function post_general_balance_logs(Request $request, string $brokerId)
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

        return response()->json($this->repository->get_general_balance_logs($brokerId, $page, $count_in_page), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/billing/general/balance/logs/update/{logId}",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function update_general_balance_logs(Request $request, string $brokerId, string $logId)
    {
        $validator = Validator::make($request->all(), [
            'real_balance' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->update_general_balance_logs($brokerId, $logId, $payload), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/recalculate/logs",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function general_recalculate_logs(string $brokerId)
    {
        return response()->json($this->repository->get_general_recalculate_logs($brokerId, ['page' => 1, 'count_in_page' => 60]), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/billing/recalculate/logs",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing balance",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function post_general_recalculate_logs(Request $request, string $brokerId)
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

        return response()->json($this->repository->get_general_recalculate_logs($brokerId, $payload), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/general/settings/negative_balance",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set broker billing negative_balance",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function settings_negative_balance(Request $request, string $brokerId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_negative_balance_action($brokerId, $payload['action']);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/general/settings/credit_amount",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set broker billing credit_amount",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function settings_credit_amount(Request $request, string $brokerId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'integer', new IntegerRanges('0,100-3000')]
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_credit_amount($brokerId, $payload['amount']);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/general/manual_status",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set broker billing credit_amount",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function manual_status(Request $request, string $brokerId)
    {
        $validator = Validator::make($request->all(), [
            'billing_manual_status' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_manual_status($brokerId, $payload['billing_manual_status']);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/general/logs/all",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing logs",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function logs(Request $request, string $brokerId)
    {
        $validator = Validator::make($request->all(), [
            'extended' => 'nullable|boolean',
            'collection' => 'nullable|string',
            'limit' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $collection = $this->repository->get_change_logs($brokerId, $payload['extended'] ?? false, $payload['collection'] ?? '', $payload['limit'] ?? 20);

        return response()->json($collection, 200);
    }
}
