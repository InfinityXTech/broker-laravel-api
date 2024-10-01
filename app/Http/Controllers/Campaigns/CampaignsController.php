<?php

namespace App\Http\Controllers\Campaigns;

use Illuminate\Http\Request;
use App\Helpers\ClientHelper;

use App\Helpers\GeneralHelper;
use App\Helpers\StorageHelper;

use OpenApi\Annotations as OA;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Settings\SettingsRepository;
use App\Repository\Campaigns\ICampaignsRepository;
use App\Models\Campaigns\MarketingCampaignEndpointAllocations;

/**
 * @OA\PathItem(
 * path="/api/campaigns"
 * )
 * @OA\Tag(
 *     name="campaign",
 *     description="User related operations"
 * )
 */
class CampaignsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ICampaignsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        // $this->middleware('permissions:campaigns[active=1]', []);
        // // view
        // $this->middleware('permissions:campaigns[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_name', 'daily_cr', 'caps', 'all_caps']]);
        // // create
        // $this->middleware('permissions:campaigns[access=all|access=add]', ['only' => ['create']]);
        // // update
        // $this->middleware('permissions:campaigns[access=all|access=edit]', ['only' => ['update', 'archive', 'update_caps']]);

        // $this->middleware('permissions:campaigns[daily_cap]', ['only' => ['caps', 'all_caps']]);

        // $this->middleware('permissions:campaigns[general]', ['only' => ['update', 'archive']]);

        // $this->middleware('permissions:campaigns[unpayable_leads]', ['only' => ['un_payable_leads']]);

        // $this->middleware('permissions:campaigns[conversion_rates]', ['only' => ['conversion_rates']]);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/campaigns",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all campaigns",
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
            "status",
            "name",
            "token",
            "tags",
            "account_manager",
            "world_wide_targeting",
            "created_by",
            "created_at"
        ];

        $relations = [
            "created_by_user:name,account_email",
            "targeting_data" //country_code,country_name,region_codes,region_name
        ];

        return response()->json($this->repository->index($advertiserId, $columns, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get campaign",
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
    public function get(string $advertiserId, string $campaignId)
    {
        $model = $this->repository->findById($campaignId);
        $model->restrict_type = $model->restrict_type ?? null;
        $model->restrict_endpoints = $model->restrict_endpoints ?? null;
        StorageHelper::injectFile('marketing_offer', $model, 'screenshot_image');
        return response()->json($model, 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/campaigns/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"campaign"},
     *  summary="Create campaign",
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
    public function create(Request $request, string $advertiserId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['advertiserId'] = $advertiserId;

        $model = $this->repository->create($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/advertisers/{advertiserId}/campaigns/update/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Post campaign",
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
    public function update(Request $request, string $advertiserId, string $campaignId)
    {
        $payload = $request->all();

        $links = ['tracking_link', 'tracking_preview_link'];
        $config = ClientHelper::clientConfig();
        $settings_repository = new SettingsRepository();
        $settings_model = $settings_repository->get_settings_model();
        foreach ($links as $link) {
            if (!empty($payload[$link])) {
                $payload[$link] = str_replace('{marketing_tracking_domain}', $config['marketing_tracking_domain'], $payload[$link]);
                $payload[$link] = str_replace('{marketing_suite_tracking_domain}', $settings_model->marketing_suite_tracking_url ?? '', $payload[$link]);
                $payload[$link] = preg_replace('#\{([^}]+)\}#', '!!!${1}!!!', $payload[$link]);
            }
        }

        // if (!empty($payload['tracking_preview_link'])) {
        //     $payload['tracking_preview_link'] = preg_replace('#\{([^}]+)\}#', '!!!${1}!!!', $payload['tracking_preview_link']);
        // }

        if (!empty($payload['tracking_country_link']) && !is_array($payload['tracking_country_link'])) {
            $payload['tracking_country_link'] = json_decode($payload['tracking_country_link'], true);
        }

        $validator = Validator::make($payload, [
            'name' => 'required|string|min:4',
            'status' => 'required|integer',
            'account_manager' => 'nullable|string',
            "description" => 'nullable|string',
            "environment" => 'required|string',
            "desktop_operating_system" => 'nullable|string',
            "mobile_operating_system" => 'nullable|string',
            "event_type" => 'required|string',
            "post_event" => 'nullable|string',
            "category" => 'nullable|string',
            "tracking_link" => 'nullable|url',
            "tracking_preview_link" => 'nullable|url',
            "tracking_country_link" => 'nullable|array',
            'screenshot_image' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload_orig = $request->all();
        foreach ($links as $link) {
            if (!empty($payload_orig[$link])) {
                $payload[$link] = $payload_orig[$link];//preg_replace('#\!\!\!([^!]+)\!\!\!#', '{${1}}', $payload[$link]);
            }
        }

        $payload['status'] = (string)$payload['status'];

        if (($payload['event_type'] ?? '') != 'cpa') {
            $payload['post_event'] = '';
        }

        $payload['tracking_country_link'] = array_filter($payload['tracking_country_link'] ?? [], fn ($var) => ($var !== NULL && $var !== FALSE && $var !== ""));

        switch ($payload['environment'] ?? '') {
            case 'all': {
                    $payload['mobile_operating_system'] = '';
                    $payload['desktop_operating_system'] = '';
                    break;
                }
            case 'desktop': {
                    if (isset($payload['mobile_operating_system'])) {
                        $payload['mobile_operating_system'] = '';
                    }
                    break;
                }
            case 'mobile': {
                    if (isset($payload['desktop_operating_system'])) {
                        $payload['desktop_operating_system'] = '';
                    }
                    break;
                }
        }

        $result = $this->repository->update($campaignId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/tags/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Post campaign",
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
    public function get_tags(Request $request, string $advertiserId, string $campaignId)
    {
        $result = $this->repository->findById($campaignId, ['tags']);

        return response()->json([
            'success' => true,
            'tags' => $result->tags ?? []
        ], 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/campaigns/tags/update/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Post campaign",
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
    public function set_tags(Request $request, string $advertiserId, string $campaignId)
    {
        $payload = $request->all();

        $validator = Validator::make($payload, [
            'tags' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($campaignId, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    private function parse_raw_http_request(array &$a_data): void
    {
        // read incoming data
        $input = file_get_contents('php://input');

        // grab multipart boundary from content type header
        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        $boundary = $matches[1];

        // split content by boundary and get rid of last -- element
        $a_blocks = preg_split("/-+$boundary/", $input);
        array_pop($a_blocks);

        // loop data blocks
        foreach ($a_blocks as $id => $block) {
            if (empty($block))
                continue;

            // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

            // parse uploaded files
            if (strpos($block, 'application/octet-stream') !== FALSE) {
                // match "name", then everything after "stream" (optional) except for prepending newlines 
                preg_match('/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s', $block, $matches);
            }
            // parse all other fields
            else {
                // match "name" and optional value in between newline sequences
                preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
            }
            $a_data[$matches[1]] = $matches[2] ?? '';
        }
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/campaigns/general_payout/update/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function general_payout_update(Request $request, string $advertiserId, string $campaignId)
    {
        $payload = $request->all();
        if (empty($payload)) {
            $this->parse_raw_http_request($payload);
        }

        $validator = Validator::make($payload, [
            'affiliate_general_payout' => 'required|numeric|lt:advertiser_general_payout',
            'advertiser_general_payout' => 'nullable|numeric|gt:affiliate_general_payout',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($campaignId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/budget/update/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function budget_update(Request $request, string $advertiserId, string $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'general_cap' => 'nullable|numeric|gt:0',
            'daily_cap' => 'nullable|numeric|gt:0',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {

            if ((float)(($payload['daily_cap'] ?? -1)) > 0) {
                $all_cap = 0;

                $query = MarketingCampaignEndpointAllocations::query()->where('campaign', '=', $campaignId);
                $caps = $query->get(['daily_cap']);
                foreach ($caps as $_cap) {
                    $all_cap += (float)$_cap->daily_cap ?? 0;
                }
                if ((float)$payload['daily_cap'] < ((float)$all_cap)) {
                    throw new \Exception('You cannot set this value because the sum caps of "Traffic Endpoint Allocation" is greater than General Daily Cap');
                }
            }

            $result = [];
            $result = $this->repository->update($campaignId, $payload);

            return response()->json([
                'success' => $result
            ], 200);
        } catch (\Exception $ex) {
            return response()->json(['daily_cap' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/campaigns/limitation/update/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function limitation_update(Request $request, string $advertiserId, string $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'time_start' => 'nullable|array',
            'time_end' => 'nullable|array',
            'blocked_schedule' => 'nullable|array',
            'force_sub_publisher' => 'nullable|boolean',
            'check_max_mind' => 'nullable|boolean'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (isset($payload['time_start'])) {
            $dt = GeneralHelper::GeDateFromTimestamp($payload['time_start']);
            $payload['time_start'] = GeneralHelper::ToMongoDateTime(date('Y-m-d 00:00:00', $dt));
        }

        if (isset($payload['time_end'])) {
            $dt = GeneralHelper::GeDateFromTimestamp($payload['time_end']);
            $payload['time_end'] = GeneralHelper::ToMongoDateTime(date('Y-m-d 11:59:59', $dt));
        }

        $result = $this->repository->update($campaignId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/limitation/force_sub_publisher/update",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function limitation_force_sub_publisher_update(Request $request, string $advertiserId, string $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'force_sub_publisher' => 'required|bool',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($campaignId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/targeting/world_wide/update",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function targeting_world_wide_update(Request $request, string $advertiserId, string $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'world_wide_targeting' => 'required|bool',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($campaignId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/{campaignId}/endpoint_managment/update",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function endpoint_managment_update(Request $request, string $advertiserId, string $campaignId)
    {
        $validator = Validator::make($request->all(), [
            'restrict_type' => 'nullable|string',
            'restrict_endpoints' => 'nullable|array|', //required_with:restrict_type
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['restrict_type']) && empty($payload['restrict_endpoints'])) {
            $payload['restrict_type'] = null;
            $payload['restrict_endpoints'] = null;
        }

        $result = $this->repository->update($campaignId, $payload);

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
     *  path="/api/advertisers/{advertiserId}/campaigns/draft/{campaignId}",
     *  tags={"campaign"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put campaign",
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
    public function draft(string $advertiserId, string $campaignId)
    {
        $result = $this->repository->draft($campaignId);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
