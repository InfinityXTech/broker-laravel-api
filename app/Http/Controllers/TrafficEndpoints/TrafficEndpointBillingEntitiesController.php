<?php

namespace App\Http\Controllers\TrafficEndpoints;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\TrafficEndpoints\ITrafficEndpointBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{trafficEndpointId}/billing/entities"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing_entities",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingEntitiesController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointBillingRepository $repository)
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

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/entities/all",
     *  tags={"traffic_endpoint_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
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
        return response()->json($this->repository->feed_billing_entities($trafficEndpointId), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/entities/create",
     *  tags={"traffic_endpoint_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
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
            'company_legal_name' => 'required|string|min:4',
            'country_code' => 'required|string|min:2',
            'region' => 'required|string|min:4',
            'city' => 'required|string|min:2',
            'zip_code' => 'required|string|min:2',
            'currency_code' => 'required|string|min:3',
            'vat_id' => 'nullable|string|min:4',
            'registration_number' => 'required|string|min:4',
            'files' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->create_billing_entities($trafficEndpointId, $payload), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/entities/update/(entityId)",
     *  tags={"traffic_endpoint_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="entityId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $trafficEndpointId, string $entityId)
    {
        $validator = Validator::make($request->all(), [
            'company_legal_name' => 'required|string|min:4',
            'country_code' => 'required|string|min:2',
            'region' => 'required|string|min:4',
            'city' => 'required|string|min:2',
            'zip_code' => 'required|string|min:2',
            'currency_code' => 'required|string|min:3',
            'vat_id' => 'nullable|string|min:4',
            'registration_number' => 'required|string|min:4',
            'files' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->update_billing_entities($trafficEndpointId, $entityId, $payload), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/entities/{entityId}",
     *  tags={"traffic_endpoint_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="entityId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get(string $trafficEndpointId, string $entityId)
    {
        return response()->json($this->repository->get_billing_entities($trafficEndpointId, $entityId), 200);
    }

    /**
     * @OA\Delete(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/entities/delete/{entityId}",
     *  tags={"traffic_endpoint_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="entityId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function remove(string $trafficEndpointId, string $entityId)
    {
        return response()->json($this->repository->remove_billing_entities($trafficEndpointId, $entityId), 200);
    }
}
