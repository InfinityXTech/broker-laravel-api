<?php

namespace App\Http\Controllers\Brokers;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerPrivateDealsRepository;
use App\Http\Controllers\ApiController;
use App\Rules\FutureDate;
use Exception;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/pyouts"
 * )
 * @OA\Tag(
 *     name="broker_crg",
 *     description="User related operations"
 * )
 */
class BrokerPrivateDealsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerPrivateDealsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'logs']]);
        // create
        $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:brokers[private_deals]', ['only' => ['index', 'get', 'logs', 'create', 'update', 'delete']]);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/private_deals/all",
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
    public function index(string $brokerId)
    {
        $result = $this->repository->index(['*'], str($brokerId));
        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/private_deals/logs/{id}",
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
    public function logs(string $brokerId, string $id)
    {
        return response()->json($this->repository->logs($id), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/private_deals/{privateDealId}",
     *  tags={"broker_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker private deal",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function get(string $brokerId, string $id)
    {
        $model = $this->repository->findById($id, ['*']);
        $model->has_leads = $this->repository->has_leads($brokerId, $id);

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
     *  path="/api/broker/{brokerId}/private_deals/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_private_deals"},
     *  summary="Create broker private deal",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(string $brokerId, Request $request)
    {
        $validator = [
            'name' => 'required|string|min:2',
            'type' => 'required|integer',
            'status' => 'boolean|nullable',
            'description' => 'nullable|string',
            // 'endpoint' => 'nullable|array',
            'endpoint' => 'required_if:type,1,4|nullable|array',
            'ignore_endpoints' => 'nullable|array',
            'only_integrations' => 'nullable|array',
            'limited_leads' => 'nullable|integer',
            'leads' => 'nullable|integer|min:1',
            'end_date' => ['nullable', new FutureDate],
            'ignore_lead_statuses' => 'nullable|array',
            'apply_crg_per_endpoint' => 'nullable|boolean',
            // 'country_code' => 'required|string|min:2',
            'country_code' => 'required|array',
            'language_code' => 'required|array',
            'min_crg' => 'required_if:type,2,3|numeric|nullable|min:1',
            'max_crg_invalid' => 'nullable|integer|min:1|max:100',
            'calc_period_crg' => 'required|integer',
            'payout' => 'required_if:type,1,4|integer|nullable|min:1',
            'blocked_schedule' => 'nullable',
            'funnel_list' => 'array|nullable',
            'sub_publisher_list' => 'array|nullable'
        ];

        $messages_validator = [
            'endpoint' => 'The Deal for Endpoints field is required',
            'min_crg' => 'Min CRG field is required',
            'payout' => 'Payout field is required'
        ];

        $type = (int)$request->input('type');
        if ($type == 1 || $type == 4) {
            unset($validator['ignore_lead_statuses'], $validator['min_crg'], $validator['calc_period_crg'], $validator['blocked_schedule']);
        }
        if ($type == 2) {
            unset($validator['payout']);
        }
        $validator = Validator::make($request->all(), $validator, $messages_validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['broker'] = $brokerId;

        foreach (['sub_publisher_list', 'funnel_list'] as $f) {
            $payload[$f] = array_filter(array_map(fn ($item) => trim($item), $payload[$f] ?? []), fn ($word) => !empty(trim($word)));
        }

        if ((count($payload['sub_publisher_list']) > 0 || count($payload['funnel_list']) > 0) && count($payload['endpoint'] ?? []) == 0) {
            return response()->json(['endpoint' => [$messages_validator['endpoint']]], 422);
        }

        try {
            $model = $this->repository->create($payload);
            return response()->json($model, 200);
        } catch (Exception $ex) {
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
     *  path="/api/broker/{brokerId}/private_deals/update/{privateDealId}",
     *  tags={"broker_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update broker private deal",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function update(string $brokerId, string $id, Request $request)
    {
        $validator = [
            'name' => 'required|string|min:2',
            'type' => 'required|integer',
            'status' => 'required',
            'description' => 'nullable|string',
            // 'endpoint' => 'nullable|array',
            'endpoint' => 'required_if:type,1,4|nullable|array',
            'ignore_endpoints' => 'nullable|array',
            'only_integrations' => 'nullable|array',
            'limited_leads' => 'nullable|integer',
            'leads' => 'nullable|integer|min:1',
            'end_date' => ['nullable', new FutureDate],
            'ignore_lead_statuses' => 'nullable|array',
            'apply_crg_per_endpoint' => 'nullable|boolean',
            // 'country_code' => 'required|string|min:2',
            'country_code' => 'required|array',
            'language_code' => 'required|array',
            'min_crg' => 'required_if:type,2,3|numeric|nullable|min:1',
            'max_crg_invalid' => 'nullable|integer|min:1|max:100',
            'calc_period_crg' => 'required|integer',
            'payout' => 'required_if:type,1,4|integer|nullable|min:1',
            'blocked_schedule' => 'nullable',
            'funnel_list' => 'array|nullable',
            'sub_publisher_list' => 'array|nullable'
        ];

        $messages_validator = [
            'endpoint' => 'The Deal for Endpoints field is required',
            'min_crg' => 'Min CRG field is required',
            'payout' => 'Payout field is required'
        ];

        $type = (int)$request->input('type');

        if ($type == 1 || $type == 4) {
            unset($validator['ignore_lead_statuses'], $validator['min_crg'], $validator['calc_period_crg'], $validator['blocked_schedule']);
        }
        if ($type == 2) {
            unset($validator['payout']);
        }
        $validator = Validator::make($request->all(), $validator, $messages_validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['broker'] = $brokerId;

        foreach (['sub_publisher_list', 'funnel_list'] as $f) {
            $payload[$f] = array_filter(array_map(fn ($item) => trim($item), $payload[$f] ?? []), fn ($word) => !empty(trim($word)));
        }

        if ((count($payload['sub_publisher_list']) > 0 || count($payload['funnel_list']) > 0) && count($payload['endpoint'] ?? []) == 0) {
            return response()->json(['endpoint' => [$messages_validator['endpoint']]], 422);
        }

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
     *  path="/api/broker/{brokerId}/private_deals/delete/{privateDealId}",
     *  tags={"broker_private_deals"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete broker private deal",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function delete(string $brokerId, string $id)
    {
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
