<?php

namespace App\Http\Controllers\Advertisers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Advertisers\IAdvertisersBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/advertisers/{advertiserId}/billing/adjustmentss"
 * )
 * @OA\Tag(
 *     name="advertisers_billing_adjustments",
 *     description="User related operations"
 * )
 */
class AdvertisersBillingAdjustmentController extends ApiController
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

        // // active
        $this->middleware('permissions:marketing_advertisers[active=1]', []);
        // view
        $this->middleware('permissions:marketing_advertisers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:marketing_advertisers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_advertisers[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        // $this->middleware('permissions:marketing_advertisers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/adjustments/all",
     *  tags={"advertisers_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all advertisers billing/adjustment",
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
    public function index(string $advertiserId)
    {
        return response()->json($this->repository->feed_adjustments($advertiserId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/adjustments/{id}",
     *  tags={"advertisers_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing/adjustment",
     *       @OA\Parameter(
     *          name="advertiserId",
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
    public function get(string $advertiserId, string $id)
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
     *  path="/api/advertisers/{advertiserId}/billing/adjustments/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"advertisers_billing_adjustments"},
     *  summary="Create advertiser billing/adjustment",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $advertiserId)
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
        $payload['advertiser'] = $advertiserId;

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
     *  path="/api/advertisers/{advertiserId}/billing/adjustments/update/{advertiserId}",
     *  tags={"advertisers_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put advertiser billing/adjustment",
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
    public function update(Request $request, string $advertiserId, string $id)
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
     *  path="/api/{advertiserId}/billing/adjustments/delete/{advertiserId}",
     *  tags={"advertisers_billing_adjustments"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put advertiser billing/adjustment",
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
    public function delete(string $advertiserId, string $id)
    {
        $result = $this->repository->delete_adjustment($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
