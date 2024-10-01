<?php

namespace App\Http\Controllers\TrafficEndpoints;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\TrafficEndpoints\ITrafficEndpointScrubRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{trafficEndpointId}/scrub"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_scrub",
 *     description="User related operations"
 * )
 */
class TrafficEndpointScrubController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointScrubRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:traffic_endpoint[active=1]', []);
        // view
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:traffic_endpoint[is_scrub_permission]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/scrub/all",
     *  tags={"traffic_endpoint_scrub"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_scrub",
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/scrub/{scrubId}",
     *  tags={"traffic_endpoint_scrub"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_scrub",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="scrubId",
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/scrub/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_scrub"},
     *  summary="Create traffic endpoint_scrub",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'TrafficEndpoint' => 'required|string',
            'country_code' => 'required|string',
            'payout' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->create($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/scrub/update/{scrubId}",
     *  tags={"traffic_endpoint_scrub"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic endpoint_scrub",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="scrubId",
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
            'country_code' => 'required|string',
            'payout' => 'required|numeric',
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/scrub/delete/{scrubId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_scrub"},
     *  summary="Delete traffic endpoint_scrub",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="scrubId",
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
