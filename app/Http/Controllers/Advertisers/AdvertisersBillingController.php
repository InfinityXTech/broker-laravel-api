<?php

namespace App\Http\Controllers\Advertisers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Advertisers\IAdvertisersBillingRepository;
use App\Http\Controllers\ApiController;
use App\Rules\IntegerRanges;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/advertisers/{advertiserId}/billing"
 * )
 * @OA\Tag(
 *     name="advertisers_billing",
 *     description="User related operations"
 * )
 */
class AdvertisersBillingController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAdvertisersBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_advertisers[active=1]', []);
        // view
        $this->middleware('permissions:marketing_advertisers[access=all|access=view|access=add|access=edit]', ['only' => ['general_balance', 'general_balance_logs', 'logs']]);
        // update
        $this->middleware('permissions:marketing_advertisers[access=all|access=edit]', ['only' => ['settings_negative_balance', 'settings_credit_amount']]);

        // $this->middleware('permissions:marketing_advertisers[is_billing]', []);
    }

    /******* GENERAL *******/

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/general/general_balance",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing balance",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function general_balance(string $advertiserId)
    {
        return response()->json($this->repository->get_general_balance($advertiserId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/general/balance/logs",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing balance",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function general_balance_logs(string $advertiserId)
    {
        return response()->json($this->repository->get_general_balance_logs($advertiserId, 1, 60), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/billing/general/balance/logs",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing balance",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function post_general_balance_logs(Request $request, string $advertiserId)
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

        return response()->json($this->repository->get_general_balance_logs($advertiserId, $page, $count_in_page), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/billing/general/balance/logs/update/{logId}",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing balance",
     *       @OA\Parameter(
     *          name="advertiserId",
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
    public function update_general_balance_logs(Request $request, string $advertiserId, string $logId)
    {
        $validator = Validator::make($request->all(), [
            'real_balance' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->update_general_balance_logs($advertiserId, $logId, $payload), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/crg/logs",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing balance",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function general_crg_logs(string $advertiserId)
    {
        return response()->json($this->repository->get_general_crg_logs($advertiserId, ['page' => 1, 'count_in_page' => 60]), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/billing/crg/logs",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing balance",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function post_general_crg_logs(Request $request, string $advertiserId)
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

        return response()->json($this->repository->get_general_crg_logs($advertiserId, $payload), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/billing/general/settings/negative_balance",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set advertiser billing negative_balance",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function settings_negative_balance(Request $request, string $advertiserId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_negative_balance_action($advertiserId, $payload['action']);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/billing/general/settings/credit_amount",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set advertiser billing credit_amount",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function settings_credit_amount(Request $request, string $advertiserId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'integer', new IntegerRanges('0,100-3000')]
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_credit_amount($advertiserId, $payload['amount']);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/billing/general/manual_status",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set advertiser billing credit_amount",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function manual_status(Request $request, string $advertiserId)
    {
        $validator = Validator::make($request->all(), [
            'billing_manual_status' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_manual_status($advertiserId, $payload['billing_manual_status']);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/general/logs/all",
     *  tags={"advertisers_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing logs",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function logs(Request $request, string $advertiserId)
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

        $collection = $this->repository->get_change_logs($advertiserId, $payload['extended'] ?? false, $payload['collection'] ?? '', $payload['limit'] ?? 20);

        return response()->json($collection, 200);
    }
}
