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
 * path="/api/affiliates/{affiliateId}/billing/completed_transactions"
 * )
 * @OA\Tag(
 *     name="affiliates_billing_completed_transactions",
 *     description="User related operations"
 * )
 */
class AffiliateBillingCompletedTransactionsController extends ApiController
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

        // view
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['index']]);
        // update
        $this->middleware('permissions:marketing_affiliates[access=all|access=edit]', ['only' => ['select']]);

        // $this->middleware('permissions:marketing_affiliates[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/completed_transactions/all",
     *  tags={"affiliates_billing_completed_transactions"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete affiliate billing/entities",
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
        return response()->json($this->repository->feed_completed_transactions($affiliateId), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/affiliates/{affiliateId}/billing/completed_transactions/select{id}",
     *  tags={"affiliates_billing_completed_transactions"},
     *  summary="Delete affiliate billing/entities",
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
    public function select(string $affiliateId, string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }
}
