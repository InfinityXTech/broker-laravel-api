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
 * path="/api/advertisers/{advertiserId}/billing/completed_transactions"
 * )
 * @OA\Tag(
 *     name="advertisers_billing_completed_transactions",
 *     description="User related operations"
 * )
 */
class AdvertisersBillingCompletedTransactionsController extends ApiController
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
        $this->middleware('permissions:marketing_advertisers[access=all|access=view|access=add|access=edit]', ['only' => ['index']]);
        // update
        $this->middleware('permissions:marketing_advertisers[access=all|access=edit]', ['only' => ['select']]);

        // $this->middleware('permissions:marketing_advertisers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/completed_transactions/all",
     *  tags={"advertisers_billing_completed_transactions"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete advertiser billing/entities",
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
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/billing/completed_transactions/select{id}",
     *  tags={"advertisers_billing_completed_transactions"},
     *  summary="Delete advertiser billing/entities",
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
    public function select(string $advertiserId, string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }
}
