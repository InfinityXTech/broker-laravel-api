<?php

namespace App\Http\Controllers\Advertisers;

use Illuminate\Http\Request;
use App\Helpers\ClientHelper;

use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;

use OpenApi\Annotations as OA;
use App\Classes\DataTransformer;
use App\Models\MarketingCampaign;
use App\Models\MarketingAffiliate;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Advertisers\IAdvertisersRepository;

/**
 * @OA\PathItem(
 * path="/api/advertisers"
 * )
 * @OA\Tag(
 *     name="campaign",
 *     description="User related operations"
 * )
 */
class AdvertisersController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAdvertisersRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_advertisers[active=1]', []);
        // view
        $this->middleware('permissions:marketing_advertisers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_name', 'daily_cr', 'caps', 'all_caps']]);
        // create
        $this->middleware('permissions:marketing_advertisers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_advertisers[access=all|access=edit]', ['only' => ['update', 'archive', 'update_caps']]);

        // $this->middleware('permissions:marketing_advertisers[daily_cap]', ['only' => ['caps', 'all_caps']]);

        // $this->middleware('permissions:marketing_advertisers[general]', ['only' => ['update', 'archive']]);

        // $this->middleware('permissions:marketing_advertisers[unpayable_leads]', ['only' => ['un_payable_leads']]);

        // $this->middleware('permissions:marketing_advertisers[conversion_rates]', ['only' => ['conversion_rates']]);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/all",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all advertisers",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {

        $columns = [
            "_id",
            "status",
            "name",
            "token",
            "account_manager",
            "created_by",
            "created_at",
            "in_house"
        ];

        $relations = [
            "created_by_user:name,account_email",
        ];

        return response()->json($this->repository->index($columns, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
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
    public function get(string $id)
    {
        $model = $this->repository->findById($id);
        return response()->json($model, 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/advertisers/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"campaign"},
     *  summary="Create campaign",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4',
            'email' => 'required|string|email',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $crypt = new DataTransformer();
        $payload['email_encrypted'] = $crypt->encrypt($payload['email']);
        $payload['email_md5'] = md5($payload['email']);
        unset($payload['email']);

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
     *  path="/api/advertisers/update/{advertiserId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4',
            'status' => 'required|integer',
            'account_manager' => 'nullable|string',
            "description" => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['status'] = (string)$payload['status'];

        $result = $this->repository->update($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/advertisers/email/{advertiserId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
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
    public function get_email(string $id)
    {
        $model = $this->repository->findById($id);

        $email = $model['email'] ?? '';
        if (isset($model['email_encrypted'])) {
            $crypt = new DataTransformer();
            $email = $crypt->decrypt($model['email_encrypted']);
        }

        return response()->json(['email' => $email], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/advertisers/tracking_link/{advertiserId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
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
    public function tracking_link(Request $request, string $advertiserId)
    {
        $validator = Validator::make($request->all(), [
            'affiliateId' => 'required|string',
            'campaignId' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $affiliateId = $payload['affiliateId'] ?? '';
        $affiliateToken = '';
        if (!empty($affiliateId)) {
            $model = MarketingAffiliate::query()->find($affiliateId, ['token']);
            $affiliateToken = $model->token;
        }

        $campaignId = $payload['campaignId'] ?? '';
        $campaignToken = '';
        if (!empty($campaignId)) {
            $model = MarketingCampaign::query()->find($campaignId, ['token']);
            $campaignToken = $model->token;
        }

        $clientConfig = ClientHelper::clientConfig();
        $link = $clientConfig['marketing_tracking_domain'] . '/click?aff=' . (!empty($affiliateToken) ? $affiliateToken : $affiliateId) . '&campaign=' . (!empty($campaignToken) ? $campaignToken : $campaignId);

        return response()->json(['link' => strtolower($link)], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/advertisers/un_payable",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="set un_payable",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function un_payable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clickIds' => 'string|min:2',
            'reason_change' => 'string|nullable',
            'reason_change2' => 'string|nullable'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->un_payable($payload);

        return response()->json($result, 200);
    }

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Patch(
     *  path="/api/advertisers/draft/{advertiserId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function draft(string $advertiserId)
    {
        $result = $this->repository->draft($advertiserId);

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
     *  path="/api/advertisers/delete/{advertiserId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function delete(string $advertiserId)
    {
        $result = $this->repository->delete($advertiserId);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
