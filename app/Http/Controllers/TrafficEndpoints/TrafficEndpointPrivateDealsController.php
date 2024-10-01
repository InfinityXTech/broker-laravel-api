<?php

namespace App\Http\Controllers\TrafficEndpoints;

use Illuminate\Http\Request;
use App\Helpers\GeneralHelper;
// use Illuminate\Support\Facades\Route;

use OpenApi\Annotations as OA;
use App\Http\Controllers\ApiController;

use Illuminate\Support\Facades\Validator;
use App\Repository\TrafficEndpoints\ITrafficEndpointPrivateDealsRepository;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint{trafficEndpointId}/private_deals"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_private_deals",
 *     description="User related operations"
 * )
 */
class TrafficEndpointPrivateDealsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointPrivateDealsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:traffic_endpoint[active=1]', []);
        // view
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'logs']]);
        // create
        $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:traffic_endpoint[private_deals]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint{trafficEndpointId}/private_deals/all",
     *  tags={"traffic_endpoint_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic_endpoint private deals",
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
        $result = $this->repository->index(['*'], str($trafficEndpointId));
        foreach ($result as &$model) {
            if (is_string($model['country_code']) && !empty($model['country_code'])) {
                $model['country_code'] = [$model['country_code']];
            }
            if (is_string($model['language_code']) && !empty($model['language_code'])) {
                $model['language_code'] = [$model['language_code']];
            }
        }

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{trafficEndpointId}/private_deals/all",
     *  tags={"broker_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker private deals",
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
    public function logs(string $trafficEndpointId, string $id)
    {
        return response()->json($this->repository->logs($id), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint{trafficEndpointId}/private_deals/{privateDealId}",
     *  tags={"traffic_endpoint_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic_endpoint private deal",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="privateDealId",
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
        $model = $this->repository->findById($id, ['*']);
        $model->has_leads = $this->repository->has_leads($trafficEndpointId, $id);
        if (is_array($model->ignore_lead_statuses) && count($model->ignore_lead_statuses) > 0) {
            $model->ignore_lead_statuses = array_values(array_filter($model->ignore_lead_statuses ?? [], fn ($value) => !is_null($value) && $value !== ''));
        }

        if (is_string($model->country_code) && !empty($model->country_code)) {
            $model->country_code = [$model->country_code];
        }

        if (is_string($model->language_code) && !empty($model->country_code)) {
            $model->language_code = [$model->language_code];
        }

        return response()->json($model, 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint{trafficEndpointId}/private_deals/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint_private_deals"},
     *  summary="Create traffic_endpoint private deal",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(string $trafficEndpointId, Request $request)
    {
        $validator = [
            'name' => 'required|string|min:2',
            'type' => 'required|integer',
            'status' => 'boolean|nullable',
            'description' => 'nullable|string',
            'endpoint' => 'nullable|array',
            'ignore_endpoints' => 'nullable|array',
            'only_integrations' => 'nullable|array',
            'limited_leads' => 'nullable|integer',
            'leads' => 'nullable|integer',
            'end_date' => 'nullable',
            'ignore_lead_statuses' => 'nullable|array',
            'apply_crg_per_endpoint' => 'nullable|boolean',
            // 'country_code' => 'required|string|min:2',
            'country_code' => 'required|array',
            'language_code' => 'required|array',
            'min_crg' => 'required|integer|min:1|gt:0',
            'max_crg_invalid' => 'nullable|integer|min:1|max:100',
            'calc_period_crg' => 'required|integer',
            'payout' => 'required|integer',
            'blocked_schedule' => 'nullable',
            'funnel_list' => 'array|nullable',
            'sub_publisher_list' => 'array|nullable'
        ];
        if ($request->input('type') == '1') {
            unset($validator['ignore_lead_statuses'], $validator['min_crg'], $validator['calc_period_crg'], $validator['blocked_schedule']);
        }
        if ($request->input('type') == '2') {
            unset($validator['payout']);
        }
        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['end_date'])) {
            $payload['end_date'] = GeneralHelper::ToMongoDateTime($payload['end_date']);
        }

        $payload['TrafficEndpoint'] = $trafficEndpointId;

        foreach (['sub_publisher_list', 'funnel_list'] as $f) {
            $payload[$f] = array_filter(array_map(fn ($item) => trim($item), $payload[$f] ?? []), fn ($word) => !empty(trim($word)));
        }

        try {
            $model = $this->repository->create($payload);
            return response()->json($model, 200);
        } catch (\Exception $ex) {
            return response()->json(['country_language' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint{trafficEndpointId}/private_deals/update/{privateDealId}",
     *  tags={"traffic_endpoint_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update traffic_endpoint private deal",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="privateDealId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update(string $trafficEndpointId, string $id, Request $request)
    {
        $validator = [
            'name' => 'required|string|min:2',
            'type' => 'required|integer',
            'status' => 'boolean|nullable',
            'description' => 'nullable|string',
            'endpoint' => 'nullable|array',
            'ignore_endpoints' => 'nullable|array',
            'only_integrations' => 'nullable|array',
            'limited_leads' => 'nullable|integer',
            'leads' => 'nullable|integer',
            'end_date' => 'nullable',
            'ignore_lead_statuses' => 'nullable|array',
            'apply_crg_per_endpoint' => 'nullable|boolean',
            // 'country_code' => 'required|string|min:2',
            'country_code' => 'required|array',
            'language_code' => 'required|array',
            'min_crg' => 'required|integer|min:1|gt:0',
            'max_crg_invalid' => 'nullable|integer|min:1|max:100',
            'calc_period_crg' => 'required|integer',
            'payout' => 'required|integer',
            'blocked_schedule' => 'nullable',
            'funnel_list' => 'array|nullable',
            'sub_publisher_list' => 'array|nullable'
        ];
        if ($request->input('type') == '1') {
            unset($validator['ignore_lead_statuses'], $validator['min_crg'], $validator['calc_period_crg'], $validator['blocked_schedule']);
        }
        if ($request->input('type') == '2') {
            unset($validator['payout']);
        }
        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['end_date'])) {
            $payload['end_date'] = GeneralHelper::ToMongoDateTime($payload['end_date']);
        }

        $payload['TrafficEndpoint'] = $trafficEndpointId;

        try {
            $result = $this->repository->update($id, $payload);
            return response()->json([
                'success' => $result
            ], 200);
        } catch (\Exception $ex) {
            return response()->json(['country_language' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/traffic_endpoint{trafficEndpointId}/private_deals/delete/{privateDealId}",
     *  tags={"traffic_endpoint_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete traffic_endpoint private deal",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="privateDealId",
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
