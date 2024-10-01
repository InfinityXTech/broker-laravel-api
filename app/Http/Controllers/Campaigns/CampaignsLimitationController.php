<?php

namespace App\Http\Controllers\Campaigns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\ApiController;
use App\Repository\Campaigns\ICampaignsLimitationRepository;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/limitations"
 * )
 * @OA\Tag(
 *     name="campaign_limitations",
 *     description="User related operations"
 * )
 */
class CampaignsLimitationController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ICampaignsLimitationRepository $repository)
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

        // $this->middleware('permissions:campaigns[limitations]', ['only' => ['index', 'get', 'create', 'update', 'enable', 'delete']]);

    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers",
     *  tags={"campaign_limitations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign limitations",
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
    public function sub_publishers(string $advertiserId, string $campaignId)
    {
        $relations = ['affiliate_data'];
        return response()->json($this->repository->sub_publishers(['*'], $campaignId, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/{subPublisherId}",
     *  tags={"campaign_limitations"},
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
     *          name="subPublisherId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function sub_publishers_get(string $advertiserId, string $campaignId, string $subPublisherId)
    {
        return response()->json($this->repository->findById($subPublisherId, ['*']), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"campaign_limitations"},
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
    public function sub_publishers_create(Request $request, string $advertiserId, string $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'affiliate' => 'required|string',
            'parameter' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['campaign'] = $campaignId;

        $model = $this->repository->sub_publishers_create($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/update/{subPublisherId}",
     *  tags={"campaign_limitations"},
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
     *          name="subPublisherId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function sub_publishers_update(string $advertiserId, string $campaignId, string $subPublisherId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'affiliate' => 'required|string',
            'parameter' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->sub_publishers_update($subPublisherId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/limitations/subpublishers/delete/{subPublisherId}",
     *  tags={"campaign_limitations"},
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
     *          name="subPublisherId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function sub_publishers_delete(string $advertiserId, string $campaignId, string $subPublisherId)
    {
        $result = $this->repository->sub_publishers_delete($subPublisherId);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
