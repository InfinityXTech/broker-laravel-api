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
 * path="/api/master/{masterId}/billing/completed_transactions"
 * )
 * @OA\Tag(
 *     name="master_billing_completed_transactions",
 *     description="User related operations"
 * )
 */
class MasterBillingCompletedTransactionsController extends ApiController
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
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['index']]);
        // update
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['select']]);

        $this->middleware('permissions:masters[billing]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/completed_transactions/all",
     *  tags={"master_billing_completed_transactions"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete master billing/entities",
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
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/master/{masterId}/billing/completed_transactions/select{id}",
     *  tags={"master_billing_completed_transactions"},
     *  summary="Delete master billing/entities",
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
    public function select(string $masterId, string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }
}
