<?php

namespace App\Http\Controllers\TrafficEndpoints;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\TrafficEndpoints\ITrafficEndpointPayoutsRepository;
use App\Http\Controllers\ApiController;
use Exception;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{trafficEndpointId}/pyouts"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_payouts",
 *     description="User related operations"
 * )
 */
class TrafficEndpointPayoutsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointPayoutsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:traffic_endpoint[active=1]', []);
        // view
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'log']]);
        // create
        // $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:traffic_endpoint[payouts]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/all",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpointpayouts",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $trafficEndpointId)
    {
        return response()->json($this->repository->index(['*'], $trafficEndpointId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/{payoutId}",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function get(string $trafficEndpointId, string $id)
    {
        return response()->json($this->repository->findById($id, ['*']), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/logs/{payoutId}",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function log(string $trafficEndpointId, string $id)
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_payouts"},
     *  summary="Create traffic endpointpayout",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'TrafficEndpoint' => 'required|string|min:2',
            'cost_type' => 'required|integer',
            'country_code' => 'required|string|min:2',
            'language_code' => 'string|min:2|nullable',
            'payout' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['distributions_crg'] = true;

        try {
            $model = $this->repository->create($payload);
            return response()->json($model, 200);
        } catch (Exception $ex) {
            return response()->json(['success' => false, 'error' => $ex->getMessage()], 422);
        }
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/update/{payoutId}",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function update(Request $request, string $trafficEndpointId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'cost_type' => 'required|integer',
            'country_code' => 'required|string|min:2',
            'language_code' => 'string|min:2|nullable',
            'payout' => 'required|integer|min:0',
            'enabled' => 'boolean|nullable',
            'distributions_crg' => 'boolean|nullable',
            'weekend_off_distributions_crg' => 'boolean|nullable',
            'daily_cap' => 'numeric|nullable',
            'description' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {
            $result = $this->repository->update($id, $payload);

            return response()->json([
                'success' => $result
            ], 200);
        } catch (Exception $ex) {
            return response()->json(['success' => false, 'error' => $ex->getMessage()], 422);
        }
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/enable/{payoutId}",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function enable(Request $request, string $trafficEndpointId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'boolean|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {
            $result = $this->repository->update($id, $payload);

            return response()->json([
                'success' => $result
            ], 200);
        } catch (Exception $ex) {
            return response()->json(['success' => false, 'error' => $ex->getMessage()], 422);
        }
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/distributions_crg/{payoutId}",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function distributions_crg(Request $request, string $trafficEndpointId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'distributions_crg' => 'boolean|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {
            $result = $this->repository->update($id, $payload);

            return response()->json([
                'success' => $result
            ], 200);
        } catch (Exception $ex) {
            return response()->json(['success' => false, 'error' => $ex->getMessage()], 422);
        }
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/weekend_off_distributions_crg/{payoutId}",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function weekend_off_distributions_crg(Request $request, string $trafficEndpointId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'weekend_off_distributions_crg' => 'boolean|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {
            $result = $this->repository->update($id, $payload);

            return response()->json([
                'success' => $result
            ], 200);
        } catch (Exception $ex) {
            return response()->json(['success' => false, 'error' => $ex->getMessage()], 422);
        }
    }


    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/payouts/delete/{payoutId}",
     *  tags={"traffic_endpoint_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function delete(string $trafficEndpointId, string $id)
    {
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
