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
 * path="/api/broker/{brokerId}/billing/completed_transactions"
 * )
 * @OA\Tag(
 *     name="broker_billing_completed_transactions",
 *     description="User related operations"
 * )
 */
class BrokerBillingCompletedTransactionsController extends ApiController
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
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['select']]);

        $this->middleware('permissions:brokers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/completed_transactions/all",
     *  tags={"broker_billing_completed_transactions"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete broker billing/entities",
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
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/completed_transactions/select{id}",
     *  tags={"broker_billing_completed_transactions"},
     *  summary="Delete broker billing/entities",
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
    public function select(string $brokerId, string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }
}
