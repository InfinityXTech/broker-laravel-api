<?php

namespace App\Http\Controllers\TrafficEndpoints;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\TrafficEndpoints\ITrafficEndpointSubPublisherTokensRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{trafficEndpointId}/sub_publisher_tokens"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_sub_publisher_tokens",
 *     description="User related operations"
 * )
 */
class TrafficEndpointSubPublisherTokensController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointSubPublisherTokensRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        // $this->middleware('permissions:traffic_endpoint[active=1]', []);
        // view
        // $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // // create
        // $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // // update
        // $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        // $this->middleware('permissions:traffic_endpoint[security]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/sub_publisher_tokens/all",
     *  tags={"traffic_endpoint_sub_publisher_tokens"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_sub_publisher_tokens",
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
     * @OA\Post(
     *  path="/api/traffic_endpoint/sprav/sub_publisher_tokens",
     *  tags={"traffic_endpoint_sub_publisher_tokens"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_sub_publisher_tokens",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index_sprav(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'traffic_endpoints' => 'required|array',
            'hide_traffic_endpoint_name' => 'bool|nullable',
            'group_by_traffic_endpoint' =>  'bool|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $traffic_endpoints = $payload['traffic_endpoints'] ?? [];
        $hide_traffic_endpoint_name = (bool)($payload['hide_traffic_endpoint_name'] ?? false);
        $group_by_traffic_endpoint = (bool)($payload['group_by_traffic_endpoint'] ?? false);
        $result = [];

        $relations = [
            "traffic_endpoint_data:token",
        ];

        if ($group_by_traffic_endpoint) {
            foreach ($traffic_endpoints as $trafficEndpointId) {
                $r = $this->repository->index(['*'], $trafficEndpointId, $relations) ?? [];
                $result[$trafficEndpointId] = array_map(function ($item) use ($hide_traffic_endpoint_name) {
                    $v = $item['sub_publisher'] ?? '';
                    $t = (!$hide_traffic_endpoint_name ? $item['traffic_endpoint_data']['token'] . ': ' : '') . $item['token'] . ' (' . $v . ')';
                    return ['value' => $v, 'label' => $t];
                }, $r);
            }
        } else {

            foreach ($traffic_endpoints as $trafficEndpointId) {
                $result = array_merge($result, $this->repository->index(['*'], $trafficEndpointId, $relations) ?? []);
            }

            $result = array_reduce($result, function (?array $carry, array $item) use ($hide_traffic_endpoint_name) {
                $carry ??= [];
                $v = $item['sub_publisher'] ?? '';
                $t = (!$hide_traffic_endpoint_name ? $item['traffic_endpoint_data']['token'] . ': ' : '') . $item['token'];
                $i = array_search($v, array_column($carry, 'value'), true);
                if ($i !== false) {
                    $carry[$i]['label'] .= ', ' . $t;
                } else {
                    $carry[] = ['value' => $v, 'label' => $t];
                }
                return $carry;
            }) ?? [];

            $result = array_map(fn ($item) => ['value' => $item['value'], 'label' => $item['value'] . ' (' . $item['label'] . ')'], $result);
        }

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/sub_publisher_tokens/{securityId}",
     *  tags={"traffic_endpoint_sub_publisher_tokens"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_sub_publisher_tokens",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="securityId",
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/sub_publisher_tokens/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_sub_publisher_tokens"},
     *  summary="Create traffic endpoint_sub_publisher_tokens",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(Request $request, string $traffic_endpointId)
    {
        $validator = Validator::make($request->all(), [
            'sub_publisher' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['traffic_endpoint'] = $traffic_endpointId;
        $payload['sub_publisher'] = trim($payload['sub_publisher']);

        try {
            $model = $this->repository->create($payload);
            return response()->json($model, 200);
        } catch (\Exception $ex) {
            return response()->json(['sub_publisher' => [$ex->getMessage()]], 422);
        }

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/sub_publisher_tokens/update/{securityId}",
     *  tags={"traffic_endpoint_sub_publisher_tokens"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic endpoint_sub_publisher_tokens",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="securityId",
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
            'sub_publisher' => 'required|string',
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/sub_publisher_tokens/delete/{securityId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_sub_publisher_tokens"},
     *  summary="Delete traffic endpoint_sub_publisher_tokens",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="securityId",
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
