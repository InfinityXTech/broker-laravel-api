<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/billing/adjustmentss"
 * )
 * @OA\Tag(
 *     name="broker_billing_adjustments",
 *     description="User related operations"
 * )
 */
class BrokerBillingAdjustmentController extends ApiController
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
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:brokers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/adjustments/all",
     *  tags={"broker_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all brokers billing/adjustment",
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
    public function index(string $brokerId)
    {
        return response()->json($this->repository->feed_adjustments($brokerId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/adjustments/{id}",
     *  tags={"broker_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing/adjustment",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function get(string $brokerId, string $id)
    {
        return response()->json($this->repository->get_adjustment($id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/billing/adjustments/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_billing_adjustments"},
     *  summary="Create broker billing/adjustment",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $brokerId)
    {
        $validator = Validator::make($request->all(), [
            'amount_sign' => 'required|integer',
            'amount_value' => 'required|integer',
            'description' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['amount'] = $payload['amount_sign'] * $payload['amount_value'];
        $payload['broker'] = $brokerId;

        $model = $this->repository->create_adjustment($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/adjustments/update/{brokerId}",
     *  tags={"broker_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker billing/adjustment",
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
    public function update(Request $request, string $brokerId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'amount_sign' => 'required|integer',
            'amount_value' => 'required|integer',
            'description' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['amount'] = $payload['amount_sign'] * $payload['amount_value'];

        $result = $this->repository->update_adjustment($id, $payload);

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
     *  path="/api/{brokerId}/billing/adjustments/delete/{brokerId}",
     *  tags={"broker_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker billing/adjustment",
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
    public function delete(string $brokerId, string $id)
    {
        $result = $this->repository->delete_adjustment($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
