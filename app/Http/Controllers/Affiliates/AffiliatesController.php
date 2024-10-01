<?php

namespace App\Http\Controllers\Affiliates;

use Illuminate\Http\Request;
use App\Helpers\GeneralHelper;
use OpenApi\Annotations as OA;

use App\Classes\DataTransformer;
use App\Helpers\ClientHelper;
use App\Models\MarketingAffiliate;

use App\Http\Controllers\ApiController;
use App\Models\MarketingCampaign;
use Illuminate\Support\Facades\Validator;
use App\Repository\Affiliates\IAffiliateRepository;

/**
 * @OA\PathItem(
 * path="/api/affiliates"
 * )
 * @OA\Tag(
 *     name="affiliates",
 *     description="User related operations"
 * )
 */
class AffiliatesController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAffiliateRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_affiliates[active=1]', []);
        // view
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_name', 'daily_cr', 'caps', 'all_caps']]);
        // create
        $this->middleware('permissions:marketing_affiliates[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_affiliates[access=all|access=edit]', ['only' => ['update', 'archive', 'update_caps']]);

        // $this->middleware('permissions:marketing_affiliates[daily_cap]', ['only' => ['caps', 'all_caps']]);

        // $this->middleware('permissions:marketing_affiliates[general]', ['only' => ['update', 'archive']]);

        // $this->middleware('permissions:marketing_affiliates[unpayable_leads]', ['only' => ['un_payable_leads']]);

        // $this->middleware('permissions:marketing_affiliates[conversion_rates]', ['only' => ['conversion_rates']]);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all advertisers",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {

        $columns = ['*'];
        //     "_id",
        //     "status",
        //     // "email",
        //     "token",
        //     "account_manager",
        //     "created_by",
        //     "created_at"
        // ];

        $relations = [
            "created_by_user:name",
            "account_manager_user:name,account_email"
        ];

        return response()->json($this->repository->index($columns, $relations), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all advertisers",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function post_index(Request $request)
    {

        $columns = ['*'];
        //     "_id",
        //     "status",
        //     // "email",
        //     "token",
        //     "account_manager",
        //     "created_by",
        //     "created_at",
        //     "under_review"
        // ];

        $relations = [
            "created_by_user:name",
            "account_manager_user:name,account_email"
        ];

        $validator = Validator::make($request->all(), [
            'under_review' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // if (!empty($payload['under_review'])) {
        $payload['under_review'] = (int)$payload['under_review'];
        // }

        return response()->json($this->repository->index($columns, $relations, $payload), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
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
     * @OA\Post(
     *  path="/api/affiliates/email/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
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
     *  path="/api/affiliates/tracking_link/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function tracking_link(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $clientConfig = ClientHelper::clientConfig();
        $link = $clientConfig['marketing_tracking_domain'] . '/click?token=' . $id . '&cat=' . $payload['category'];

        return response()->json(['link' => $link], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/un_payable",
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
     * @OA\Get(
     *  path="/api/affiliates/active_categories/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function allow_categories(string $affiliateId)
    {
        $result = MarketingCampaign::query()
            ->whereIn('status', ['1', 1])
            ->whereRaw(['category' => ['$exists' => true, '$ne' => '']])
            ->get(['category', 'category_title'])
            ->map(function ($item) {
                $item->value = $item->category;
                $item->lable = $item->category_title;
                return $item;
            })->toArray();

        $unique = array_map("unserialize", array_unique(array_map("serialize", $result)));

        $result = array_merge(
            ...array_map(
                fn ($item) => [$item['value'] => $item['lable']],
                $unique
            )
        );

        return response()->json(['categories' => $result], 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/affiliates/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"affiliates"},
     *  summary="Create campaign",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
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
     *  path="/api/affiliates/update/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
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
            'email' => 'nullable|string|email',
            'status' => 'required|integer',
            'account_manager' => 'nullable|string',
            "description" => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['status'] = (string)$payload['status'];

        if (strpos($payload['email'] ?? '', '@') !== false) {
            $crypt = new DataTransformer();
            $payload['email_encrypted'] = $crypt->encrypt($payload['email']);
            $payload['email_md5'] = md5($payload['email']);

            // $count = MarketingAffiliate::query()->where('email_md5', '=', $payload['email_md5'])->get(['_id'])->count();

            // if ($count > 0) {
            //     throw new \Exception('This email already exists');
            // }
        }
        if (isset($payload['email'])) {
            unset($payload['email']);
        }

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
     *  path="/api/affiliates/credentials/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update_credentials(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), []);
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
     *  path="/api/affiliates/postbacks/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update_postbacks(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            "manual_approve" => 'nullable|bool',
            "blocked_offers_type" => 'nullable|string',
            "blocked_offers" => 'nullable|array',
            'postback' => 'nullable|string',
            'event_postback' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (empty($payload['blocked_offers'])) {
            $payload['blocked_offers_type'] = null;
            $payload['blocked_offers'] = [];
        }

        $result = $this->repository->update($id, $payload);

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
     * @OA\Patch(
     *  path="/api/affiliates/reset_password/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put affiliate",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function reset_password(string $affiliateId)
    {
        $result = $this->repository->reset_password($affiliateId);

        return response()->json($result, 200);
    }

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/affiliates/{affiliateId}/application/approve",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function application_approve(string $affiliateId)
    {
        $result = $this->repository->application_approve($affiliateId);

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
     * @OA\Put(
     *  path="/api/affiliates/{affiliateId}/application/reject",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function application_reject(string $affiliateId)
    {
        $result = $this->repository->application_reject($affiliateId);

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
     * @OA\Put(
     *  path="/api/affiliates/draft/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function draft(string $affiliateId)
    {
        $result = $this->repository->draft($affiliateId);

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
     *  path="/api/affiliates/delete/{affiliateId}",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function delete(string $affiliateId)
    {
        $result = $this->repository->delete($affiliateId);

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
     * @OA\Get(
     *  path="/api/affiliates/sprav/offers",
     *  tags={"affiliates"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function sprav_offers()
    {
        $result = $this->repository->sprav_offers();

        return response()->json($result, 200);
    }
}
