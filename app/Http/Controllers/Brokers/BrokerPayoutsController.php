<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerPayoutsRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/pyouts"
 * )
 * @OA\Tag(
 *     name="broker_payouts",
 *     description="User related operations"
 * )
 */
class BrokerPayoutsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerPayoutsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'log']]);
        // create
        // $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['create', 'update', 'enable', 'delete']]);

        $this->middleware('permissions:brokers[payouts]', ['only' => ['index', 'get', 'create', 'update', 'enable', 'delete']]);

    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/payouts/all",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker payouts",
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
        return response()->json($this->repository->index(['*'], str($brokerId)), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/payouts/{payoutId}",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker payout",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function get(string $brokerId, string $id)
    {
        return response()->json($this->repository->findById($id, ['*']), 200);
    }

     /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/payouts/logs/{payoutId}",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker payout",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function log(string $brokerId, string $id)
    {
        return response()->json($this->repository->log($id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/payouts/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_payouts"},
     *  summary="Create broker payout",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(string $brokerId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payout' => 'required|integer',
            'cost_type' => 'required|integer',
            'country_code' => 'required|string|min:2',
            'language_code' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['broker'] = $brokerId;
        $payload['language_code'] = ($payload['language_code'] ?? '');

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
     *  path="/api/broker/{brokerId}/payouts/enable/{payoutId}",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Toggle enabled status",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function enable(string $brokerId, string $id, Request $request)
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
     *  path="/api/broker/{brokerId}/payouts/update/{payoutId}",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update broker payout",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function update(string $brokerId, string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payout' => 'required|integer',
            'cost_type' => 'required|integer',
            'country_code' => 'required|string|min:2',
            'language_code' => 'nullable',
            'description' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['language_code'] = ($payload['language_code'] ?? '');
        
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
     *  path="/api/broker/{brokerId}/payouts/delete/{payoutId}",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete broker payout",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function delete(string $brokerId, string $id)
    {
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
