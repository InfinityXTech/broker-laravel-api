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
 * path="/api/affiliates/{affiliateId}/billing"
 * )
 * @OA\Tag(
 *     name="affiliates_billing",
 *     description="User related operations"
 * )
 */
class AffiliateBillingController extends ApiController
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
        
        $this->middleware('permissions:marketing_affiliates[active=1]', []);

        // // view
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['get_payment_methods']]);

        // $this->middleware('permissions:marketing_affiliates[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/sprav/payment_methods",
     *  tags={"affiliates_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate billing",
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
    public function get_payment_methods(string $affiliateId)
    {
        return $this->repository->get_payment_methods($affiliateId);
    }

    /**
     * @OA\Put(
     *  path="/api/affiliates/{affiliateId}/billing/general/manual_status",
     *  tags={"broker_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Set affiliate billing credit_amount",
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
    public function manual_status(Request $request, string $affiliateId)
    {
        $validator = Validator::make($request->all(), [
            'billing_manual_status' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set_manual_status($affiliateId, $payload['billing_manual_status']);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
