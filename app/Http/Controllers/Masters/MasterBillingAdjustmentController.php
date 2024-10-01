<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master/{masterId}/billing/adjustmentss"
 * )
 * @OA\Tag(
 *     name="master_billing_adjustments",
 *     description="User related operations"
 * )
 */
class MasterBillingAdjustmentController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMasterBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:masters[active=1]', []);
        // view
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:masters[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:masters[billing]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/adjustments/all",
     *  tags={"master_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all masters billing/adjustment",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $masterId)
    {
        return response()->json($this->repository->feed_adjustments($masterId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/adjustments/{id}",
     *  tags={"master_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master billing/adjustment",
     *       @OA\Parameter(
     *          name="masterId",
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
    public function get(string $masterId, string $id)
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
     *  path="/api/master/{masterId}/billing/adjustments/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"master_billing_adjustments"},
     *  summary="Create master billing/adjustment",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $masterId)
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
        $payload['amount'] = (float)($payload['amount_sign'] * $payload['amount_value']);
        $payload['master'] = $masterId;

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
     *  path="/api/master/{masterId}/billing/adjustments/update/{masterId}",
     *  tags={"master_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master billing/adjustment",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $masterId, string $id)
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
        $payload['amount'] = (float)($payload['amount_sign'] * $payload['amount_value']);

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
     *  path="/api/{masterId}/billing/adjustments/delete/{masterId}",
     *  tags={"master_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master billing/adjustment",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function delete(string $masterId, string $id)
    {
        $result = $this->repository->delete_adjustment($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
