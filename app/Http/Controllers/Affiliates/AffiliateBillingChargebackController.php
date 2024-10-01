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
 * path="/api/affiliates/{affiliateId}/billing/chargeback"
 * )
 * @OA\Tag(
 *     name="affiliates_billing_chargeback",
 *     description="User related operations"
 * )
 */
class AffiliateBillingChargebackController extends ApiController
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
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_payment_requests']]);
        // create
        // $this->middleware('permissions:marketing_affiliates[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_affiliates[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        // $this->middleware('permissions:marketing_affiliates[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/chargeback/all",
     *  tags={"affiliates_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all affiliates billing/chargeback",
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
    public function index(string $affiliateId)
    {
        return response()->json($this->repository->feed_chargebacks($affiliateId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/chargeback/{id}",
     *  tags={"affiliates_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate billing/chargeback",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function get(string $affiliateId, string $id)
    {
        return response()->json($this->repository->get_chargebacks($affiliateId, $id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/chargeback/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"affiliates_billing_chargeback"},
     *  summary="Create affiliate billing/chargeback",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $affiliateId)
    {
        $validator = [
            'amount' => 'required|integer',
            'payment_method' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
            'screenshots' => 'required|array',
        ];
        if (!empty($request->input('payment_request'))) {
            unset($validator['amount']);
        }
        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        $payload = $validator->validated();
        $payload['affiliate'] = $affiliateId;

        return response()->json($this->repository->create_chargebacks($affiliateId, $payload), 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/affiliates/{affiliateId}/billing/chargeback/update/{id}",
     *  tags={"affiliates_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put affiliate billing/chargeback",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function update(Request $request, string $affiliateId, string $id)
    {
        $validator = [
            'amount' => 'required|integer',
            'payment_method' => 'required|string|min:4',
            'payment_request' => 'nullable|string',
            'screenshots' => 'required|array',
        ];
        if (!empty($request->input('payment_request'))) {
            unset($validator['amount']);
        }
        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->create_chargebacks($affiliateId, $payload), 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/{affiliateId}/billing/chargeback/delete/{id}",
     *  tags={"affiliates_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put affiliate billing/chargeback",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
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
    public function delete(string $affiliateId, string $id)
    {
        return response()->json($this->repository->delete_chargebacks($affiliateId, $id), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/chargeback/sprav/payment_requests",
     *  tags={"affiliates_billing_"},
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
    public function get_payment_requests(string $affiliateId)
    {
        return $this->repository->get_payment_requests_for_chargeback($affiliateId);
    }
}
