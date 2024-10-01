<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterPayoutsRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master/{masterId}/pyouts"
 * )
 * @OA\Tag(
 *     name="master_payouts",
 *     description="User related operations"
 * )
 */
class MasterPayoutsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMasterPayoutsRepository $repository)
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
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['create', 'update', 'enable', 'delete']]);

        $this->middleware('permissions:masters[payouts]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/payouts/all",
     *  tags={"master_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master payouts",
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
        return response()->json($this->repository->index(['*'], str($masterId)), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/payouts/{payoutId}",
     *  tags={"master_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master payout",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="payoutId",
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
        return response()->json($this->repository->findById($id, ['*']), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/payouts/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"master_payouts"},
     *  summary="Create master payout",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(string $masterId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payout' => 'required|integer|gt:0',
            'cost_type' => 'required|integer',
            'country_code' => 'required|string|min:2',
            'language_code' => 'string|min:2|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['master_partner'] = $masterId;

        $model = $this->repository->create($payload);

        return response()->json($model, 200);
    }

    /**
     * Toggle enabled status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/master/{masterId}/payouts/enable/{payoutId}",
     *  tags={"master_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Toggle enabled status",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="payoutId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function enable(string $masterId, string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/master/{masterId}/payouts/update/{payoutId}",
     *  tags={"master_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update master payout",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="payoutId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update(string $masterId, string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payout' => 'required|integer|gt:0',
            'cost_type' => 'required|integer',
            'country_code' => 'required|string|min:2',
            'language_code' => 'string|min:2|nullable',
            'description' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($id, $payload);

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
     *  path="/api/master/{masterId}/payouts/delete/{payoutId}",
     *  tags={"master_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete master payout",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="payoutId",
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
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
