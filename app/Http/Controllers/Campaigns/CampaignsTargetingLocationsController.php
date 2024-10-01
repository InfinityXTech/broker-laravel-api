<?php

namespace App\Http\Controllers\Campaigns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\ApiController;
use App\Repository\Campaigns\ICampaignTargetingLocationsRepository;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/pyouts"
 * )
 * @OA\Tag(
 *     name="targeting_locations",
 *     description="User related operations"
 * )
 */
class CampaignsTargetingLocationsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ICampaignTargetingLocationsRepository $repository)
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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/targeting_locations/all",
     *  tags={"targeting_locations"},
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
    public function index(string $advertiserId, string $campaignId)
    {
        return response()->json($this->repository->index(['*'], str($campaignId)), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/targeting_locations/{targetingLocationId}",
     *  tags={"targeting_locations"},
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
     *          name="targetingLocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function get(string $advertiserId, string $campaignId, string $id)
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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/targeting_locations/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"targeting_locations"},
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
    public function create(string $advertiserId, string $campaignId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string',
            'region_codes' => 'nullable|array',
            // 'language_code' => 'nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['campaign'] = $campaignId;
        // $payload['language_code'] = ($payload['language_code'] ?? '');

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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/targeting_locations/enable/{targetingLocationId}",
     *  tags={"targeting_locations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Toggle enabled status",
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
     *          name="targetingLocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function enable(string $advertiserId, string $campaignId, string $id, Request $request)
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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/targeting_locations/update/{targetingLocationId}",
     *  tags={"targeting_locations"},
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
     *          name="targetingLocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update(string $advertiserId, string $campaignId, string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string|min:2',
            'region_codes' => 'nullable|array',
            // 'language_code' => 'nullable',
            'description' => 'nullable|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // $payload['language_code'] = ($payload['language_code'] ?? '');
        
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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/targeting_locations/delete/{targetingLocationId}",
     *  tags={"targeting_locations"},
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
     *          name="targetingLocationId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function delete(string $advertiserId, string $campaignId, string $id)
    {
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
