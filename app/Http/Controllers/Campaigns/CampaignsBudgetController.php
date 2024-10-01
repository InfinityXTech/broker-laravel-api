<?php

namespace App\Http\Controllers\Campaigns;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

use App\Models\MarketingCampaign;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Campaigns\ICampaignBudgetRepository;
use App\Models\Campaigns\MarketingCampaignEndpointAllocations;

/**
 * @OA\PathItem(
 * path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/pyouts"
 * )
 * @OA\Tag(
 *     name="campaign_budget",
 *     description="User related operations"
 * )
 */
class CampaignsBudgetController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ICampaignBudgetRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        // $this->middleware('permissions:campaigns[active=1]', []);
        // // view
        // $this->middleware('permissions:campaigns[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'log']]);
        // // create
        // // $this->middleware('permissions:campaigns[access=all|access=add]', ['only' => ['create']]);
        // // update
        // $this->middleware('permissions:campaigns[access=all|access=edit]', ['only' => ['create', 'update', 'enable', 'delete']]);

        // $this->middleware('permissions:campaigns[payouts]', ['only' => ['index', 'get', 'create', 'update', 'enable', 'delete']]);

    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocations",
     *  tags={"campaign_budget"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign payouts",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="campaignId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function endpoint_allocations(string $advertiserId, string $campaignId)
    {
        $relations = ['affiliate_data'];
        return response()->json($this->repository->endpoint_allocations(['*'], $campaignId, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocations/{allocationId}",
     *  tags={"campaign_budget"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign payout",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="campaignId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="allocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function endpoint_allocation_get(string $advertiserId, string $campaignId, string $allocationId)
    {
        return response()->json($this->repository->findById($allocationId, ['*']), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocations/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"campaign_budget"},
     *  summary="Create campaign payout",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function endpoint_allocation_create(Request $request, string $advertiserId, string $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'affiliate' => 'required|string',
            'daily_cap' => 'required|integer',
            'country_code' => 'required|string|min:2',
            // 'language_code' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['campaign'] = $campaignId;
        // $payload['language_code'] = ($payload['language_code'] ?? '');

        $model = [];
        try {
            $model = $this->repository->endpoint_allocation_create($payload);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocations/update/{allocationId}",
     *  tags={"campaign_budget"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update campaign payout",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="campaignId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="allocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function endpoint_allocation_update(string $advertiserId, string $campaignId, string $allocationId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'affiliate' => 'required|string',
            'daily_cap' => 'required|integer',
            'country_code' => 'required|string|min:2',
            // 'language_code' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {
            $result = [];
            $result = $this->repository->endpoint_allocation_update($allocationId, $payload);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }

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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocation/enable/{allocationId}",
     *  tags={"campaign_budget"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update campaign payout",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="campaignId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="allocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function endpoint_allocation_enable(string $advertiserId, string $campaignId, string $allocationId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // $payload['language_code'] = ($payload['language_code'] ?? '');

        $result = $this->repository->endpoint_allocation_update($allocationId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/budget/endpoint_allocations/delete/{allocationId}",
     *  tags={"campaign_budget"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete campaign payout",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="campaignId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="allocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function delete(string $advertiserId, string $campaignId, string $allocationId)
    {
        $result = $this->repository->endpoint_allocation_delete($allocationId);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
