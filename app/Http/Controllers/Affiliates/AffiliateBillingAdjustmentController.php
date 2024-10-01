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
 * path="/api/affiliates/{affiliatesId}/billing/adjustmentss"
 * )
 * @OA\Tag(
 *     name="affiliates_billing_adjustments",
 *     description="User related operations"
 * )
 */
class AffiliateBillingAdjustmentController extends ApiController
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
        // $this->middleware('permissions:affiliates[active=1]', []);

        // view
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:marketing_affiliates[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_affiliates[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:marketing_affiliates[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliatesId}/billing/adjustments/all",
     *  tags={"affiliates_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all affiliates billing/adjustment",
     *       @OA\Parameter(
     *          name="affiliatesId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $affiliatesId)
    {
        return response()->json($this->repository->feed_adjustments($affiliatesId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliatesId}/billing/adjustments/{id}",
     *  tags={"affiliates_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate billing/adjustment",
     *       @OA\Parameter(
     *          name="affiliatesId",
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
    public function get(string $affiliatesId, string $id)
    {
        return response()->json($this->repository->get_adjustment($affiliatesId, $id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliatesId}/billing/adjustments/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"affiliates_billing_adjustments"},
     *  summary="Create affiliate billing/adjustment",
     *       @OA\Parameter(
     *          name="affiliatesId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $affiliatesId)
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
        $payload['affiliate'] = $affiliatesId;

        $model = $this->repository->create_adjustment($affiliatesId, $payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/affiliates/{affiliatesId}/billing/adjustments/update/{affiliatesId}",
     *  tags={"affiliates_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put affiliate billing/adjustment",
     *       @OA\Parameter(
     *          name="affiliatesId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $affiliatesId, string $id)
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

        $result = $this->repository->update_adjustment($affiliatesId, $id, $payload);

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
     *  path="/api/{affiliatesId}/billing/adjustments/delete/{affiliatesId}",
     *  tags={"affiliates_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put affiliate billing/adjustment",
     *       @OA\Parameter(
     *          name="affiliatesId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function delete(string $affiliatesId, string $id)
    {
        $result = $this->repository->delete_adjustment($affiliatesId, $id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
