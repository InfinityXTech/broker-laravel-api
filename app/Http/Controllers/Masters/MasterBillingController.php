<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterBillingRepository;
use App\Http\Controllers\ApiController;
use App\Rules\IntegerRanges;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master/{masterId}/billing"
 * )
 * @OA\Tag(
 *     name="master_billing",
 *     description="User related operations"
 * )
 */
class MasterBillingController extends ApiController
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
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['general_balance', 'logs']]);

        $this->middleware('permissions:masters[billing]', []);

    }

    /******* GENERAL *******/

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/general/general_balance",
     *  tags={"master_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master billing balance",
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
    public function general_balance(string $masterId)
    {
        return response()->json($this->repository->get_general_balance($masterId), 200);
    }

    // public function general_balance_logs(string $masterId)
    // {
    //     return response()->json($this->repository->get_general_balance_logs($masterId), 200);
    // }    

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/general/logs/all",
     *  tags={"master_billing"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master billing logs",
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
    public function logs(Request $request, string $masterId)
    {
        $validator = Validator::make($request->all(), [
            'extended' => 'nullable|boolean',
            'collection' => 'nullable|string',
            'limit' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $collection = $this->repository->get_change_logs($masterId, $payload['extended'] ?? false, $payload['collection'] ?? '', $payload['limit'] ?? 20);

        return response()->json($collection, 200);
    }
}
