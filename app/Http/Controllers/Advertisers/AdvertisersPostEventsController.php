<?php

namespace App\Http\Controllers\Advertisers;

use Illuminate\Http\Request;
use App\Helpers\GeneralHelper;

use OpenApi\Annotations as OA;
use App\Http\Controllers\ApiController;

use Illuminate\Support\Facades\Validator;
use App\Repository\Advertisers\IAdvertiserPostEventsRepository;

/**
 * @OA\PathItem(
 * path="/api/advertisers"
 * )
 * @OA\Tag(
 *     name="campaign",
 *     description="User related operations"
 * )
 */
class AdvertisersPostEventsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAdvertiserPostEventsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_advertisers[active=1]', []);
        // view
        $this->middleware('permissions:marketing_advertisers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_name', 'daily_cr', 'caps', 'all_caps']]);
        // // create
        $this->middleware('permissions:marketing_advertisers[access=all|access=add]', ['only' => ['create']]);
        // // update
        $this->middleware('permissions:marketing_advertisers[access=all|access=edit]', ['only' => ['update', 'archive', 'update_caps']]);

        // $this->middleware('permissions:advertisers[daily_cap]', ['only' => ['caps', 'all_caps']]);

        // $this->middleware('permissions:advertisers[general]', ['only' => ['update', 'archive']]);

        // $this->middleware('permissions:advertisers[unpayable_leads]', ['only' => ['un_payable_leads']]);

        // $this->middleware('permissions:advertisers[conversion_rates]', ['only' => ['conversion_rates']]);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/post_events",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all advertisers",
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
    public function index(string $advertiserId)
    {

        $columns = [
            "_id",
            "name",
            "value",
            "created_by",
            "created_at"
        ];

        $relations = [
            "created_by_user:name,account_email",
        ];

        return response()->json($this->repository->index($advertiserId, $columns, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/post_events/{postEventId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="postEventId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get(string $advertiserId, string $postEventId)
    {
        $model = $this->repository->findById($postEventId);
        return response()->json($model, 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/post_events/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"campaign"},
     *  summary="Create campaign",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $advertiserId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2',
            'value' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['advertiser'] = $advertiserId;

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
     *  path="/api/advertisers/{advertiserId}/post_events/update/{postEventId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="postEventId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $advertiserId, string $postEventId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2',
            'value' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($postEventId, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/advertisers/{advertiserId}/post_events/delete/{postEventId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="postEventId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function delete(string $advertiserId, string $postEventId)
    {
        $result = $this->repository->delete($postEventId);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
