<?php

namespace App\Http\Controllers\TrafficEndpoints;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Repository\TrafficEndpoints\ITrafficEndpointDynamicIntegrationIDsRepository;
use App\Http\Controllers\ApiController;
use Exception;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{trafficEndpointId}/dynamic_integration_ids"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_dynamic_integration_ids",
 *     description="User related operations"
 * )
 */
class TrafficEndpointDynamicIntegrationIDsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointDynamicIntegrationIDsRepository $repository)
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

        // $this->middleware('permissions:traffic_endpoint[dynamic_integration_ids]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/dynamic_integration_ids",
     *  tags={"traffic_endpoint_dynamic_integration_ids"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpointdynamic_integration_ids",
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
        $relations = [
            'broker_data:partner_name,token,created_by,account_manager',
            'integration_data:name',
        ];
        return response()->json($this->repository->index(['*'], $trafficEndpointId, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/dynamic_integration_ids/{dynamicIntegrationId}",
     *  tags={"traffic_endpoint_dynamic_integration_ids"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="dynamicIntegrationId",
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
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/dynamic_integration_ids/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_dynamic_integration_ids"},
     *  summary="Create traffic endpointpayout",
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
    public function create(Request $request, string $trafficEndpointId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer',
            'brokerId' => 'required|string|min:2',
            'integrationId' => 'required|string|min:2',
            'DV1' => 'required|string',
            'DV2' => 'string|nullable',
            'DV3' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['TrafficEndpoint'] = $trafficEndpointId;

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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/dynamic_integration_ids/update/{dynamicIntegrationId}",
     *  tags={"traffic_endpoint_dynamic_integration_ids"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="dynamicIntegrationId",
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
            'status' => 'required|integer',
            'brokerId' => 'required|string|min:2',
            'integrationId' => 'required|string|min:2',
            'DV1' => 'required|string',
            'DV2' => 'string|nullable',
            'DV3' => 'string|nullable',
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/dynamic_integration_ids/delete/{dynamicIntegrationId}",
     *  tags={"traffic_endpoint_dynamic_integration_ids"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete traffic endpointpayout",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="dynamicIntegrationId",
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
