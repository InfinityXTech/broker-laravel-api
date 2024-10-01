<?php

namespace App\Http\Controllers\TrafficEndpoints;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
// use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ApiController;

use Illuminate\Support\Facades\Validator;
use App\Repository\TrafficEndpoints\ITrafficEndpointRepository;
use MongoDB\Operation\Update;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint",
 *     description="User related operations"
 * )
 */
class TrafficEndpointController extends ApiController
{
    private $repository;

    private $default_dashboard_permissions_list = [
        'intelligence' => 'Intelligence',
        'insights' => 'Insights',
        'api' => 'API',
        'postbacks' => 'Postbacks',
        'payouts' => 'Payouts',
    ];

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:traffic_endpoint[active=1]', []);
        // view
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'offers_access_get', 'feed_visualization_get', 'lead_analisis']]);
        // create
        $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['update', 'reset_password', 'archive']]);

        $this->middleware('permissions:traffic_endpoint[general]', ['only' => ['update']]);
        $this->middleware('permissions:traffic_endpoint[feed_visualization]', ['only' => ['feed_visualization_get']]);

        // $this->middleware('permissions:traffic_endpoint[lead_analysis]', ['only' => ['lead_analisis']]);
        // $this->middleware('permissions:traffic_endpoint[response_tools]', ['only' => ['response_tools']]);
        $this->middleware('roles:tech_support|integration_manager', ['only' => ['lead_analisis']]);
        $this->middleware('roles:tech_support|integration_manager', ['only' => ['response_tools']]);

        $this->middleware('permissions:traffic_endpoint[marketing_suite]', ['only' => ['offers_access_get']]);
        $this->middleware('permissions:traffic_endpoint[unpayable_leads]', ['only' => ['un_payable_leads']]);
        $this->middleware('permissions:traffic_endpoint[broker_simulator]', ['only' => ['broker_simulator']]);

        $this->middleware('permissions:traffic_endpoint[integration|setup]', ['only' => ['reset_password', 'get_integration', 'update_integration']]);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/all",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        $columns = [
            // '*',
            "_id",
            "account_manager",
            "created_by",
            "status",
            "token",
            "today_leads",
            "total_leads",
            "today_deposits",
            "total_deposits",
            "today_revenue",
            "total_revenue",
            "probation",
            "in_house",
            "tags",
            "deactivation_reason"
        ];
        $relations = [
            "account_manager_user:name,account_email",
            "created_by_user:name,account_email"
        ];

        return response()->json($this->repository->index($columns, $relations), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/all",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function post_index(Request $request)
    {
        $columns = [
            // '*',
            "_id",
            "account_manager",
            "created_by",
            "status",
            "token",
            "today_leads",
            "total_leads",
            "today_deposits",
            "total_deposits",
            "today_revenue",
            "total_revenue",
            "UnderReview",
            "ApplicationJson",
            // "probation"
        ];
        $relations = [
            "account_manager_user:name,account_email",
            "created_by_user:name,account_email"
        ];

        $validator = Validator::make($request->all(), [
            'UnderReview' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        if (isset($payload['UnderReview'])) {
            $payload['UnderReview'] = explode(',', $payload['UnderReview']);
            $payload['UnderReview'] = array_map('intval', $payload['UnderReview']);
        }

        return response()->json($this->repository->index($columns, $relations, $payload), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic_endpoint",
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
    public function get(string $id)
    {
        $model = $this->repository->findById($id);
        // $model->_probation = (int)($model->probation ?? 0) == 2 ? true : false;

        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/integrations",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic_endpoint",
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
    public function get_integration(string $id)
    {
        $result = $this->repository->findById($id)->toArray();

        if (!isset($result['permissions'])) {
            $result['permissions'] = array_keys($this->default_dashboard_permissions_list);
        }

        return response()->json($result, 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"traffic_endpoint"},
     *  summary="Create traffic_endpoint",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_type' => 'nullable|integer',
            'traffic_quality' => 'nullable|integer',
            // 'country' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['traffic_quality'] = $payload['traffic_quality'] ?? 1;

        if (!isset($payload['permissions'])) {
            $payload['permissions'] = array_keys($this->default_dashboard_permissions_list);
        }

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
     *  path="/api/traffic_endpoint/update/{trafficEndpointId}",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint",
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
    public function update(Request $request, string $id)
    {
        Validator::extend('replace_funnel_list_no_empty', function ($attribute, $value, $parameters) {
            foreach ($value as $v) {
                if (empty($v['from']) || empty($v['to'])) {
                    return false;
                }
            }
            return true;
        }, 'Replace Funnel List must be with all data');

        Validator::extend('list_links', function ($attribute, $value, $parameters) {
            $urls = explode(PHP_EOL, $value);
            foreach ($urls as $url) {
                $url = trim($url);
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return false;
                }
            }
            return true;
        }, 'One of the URLs is not validate');

        Validator::extend('restricted_brokers_by_country', function ($attribute, $value, $parameters) {
            foreach ($value as $list) {
                foreach ($list as $country => $values) {
                    if (empty($country)) {
                        return false;
                    }
                }
            }
            return true;
        }, 'Restricted Brokers By Country not filled completely');

        $validator = Validator::make($request->all(), [
            'status' => 'required|integer',
            'UnderReview' => 'nullable|integer',
            'account_manager' => 'string|nullable',
            'master_partner' => 'string|nullable',
            'replace_funnel_list' => 'array|nullable|replace_funnel_list_no_empty',
            'blocked_funnels' => 'array|nullable',
            'restricted_brokers' => 'array|nullable',
            'restricted_brokers_by_country' => 'array|nullable|restricted_brokers_by_country',
            'country' => 'string|nullable',
            'traffic_sources' => 'array|nullable',
            'permissions' => 'array|nullable',
            'endpoint_type' => 'integer|nullable',
            'endpoint_status' => 'string|nullable',
            'traffic_quality' => 'nullable|integer',
            'postback' => 'string|list_links|nullable',
            'lead_postback' => 'string|list_links|nullable',
            'statusMatching' => 'boolean|nullable',
            'statusReporting' => 'boolean|nullable',
            'statusDeposit' => 'boolean|nullable',
            'redirect_24_7' => 'boolean|nullable',
            // '_probation' => 'boolean|nullable',
            'tags' => 'nullable|array',
            'deactivation_reason' => 'required_if:status,0|string',
            'deactivation_reason_duplicated' => 'required_if:deactivation_reason,Duplicated endpoint|string',
            'deactivation_reason_other' => 'required_if:deactivation_reason,other|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // $payload = $request->all();

        // $result = true;
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/application/approve",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint",
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
    public function application_approve(string $id)
    {
        $payload = ['UnderReview' => 1];
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
     *  path="/api/traffic_endpoint/{trafficEndpointId}/application/reject",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint",
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
    public function application_reject(string $id)
    {
        $payload = ['UnderReview' => 2];
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
     *  path="/api/traffic_endpoint/update/{trafficEndpointId}/integrations",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint",
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
    public function update_integration(Request $request, string $id)
    {
        return $this->update($request, $id);
    }

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Patch(
     *  path="/api/traffic_endpoint/archive/{trafficEndpointId}",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint",
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
    public function archive(string $id)
    {
        $result = $this->repository->archive($id);

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
     *  path="/api/traffic_endpoint/reset_password/{trafficEndpointId}",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put traffic_endpoint",
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
    public function reset_password(string $id)
    {
        $result = $this->repository->reset_password($id);

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/offers/access",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
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
    public function offers_access_get(string $trafficEndpointId)
    {
        $columns = [
            "_id",
            "name",
            "token",
        ];
        $relations = [
            "created_by_user:name,account_email"
        ];
        return response()->json($this->repository->offers_access_get($columns, $trafficEndpointId, $relations), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/offers/access",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
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
    public function offers_access_update(Request $request, string $trafficEndpointId)
    {
        // $validator = Validator::make($request->all(), [
        // ]);
        // if ($validator->fails()) {
        //     return response()->json($validator->errors(), 422);
        // }

        // $payload = $validator->validated();
        $payload = $request->all();

        $result = $this->repository->offers_access_update($trafficEndpointId, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/broker_simulator_group_by_fields",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function broker_simulator_group_by_fields()
    {
        $result = $this->repository->feed_visualization_group_by_fields('');
        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/feed_visualization_group_by_fields",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
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
    public function feed_visualization_group_by_fields(string $trafficEndpointId)
    {
        $result = $this->repository->feed_visualization_group_by_fields($trafficEndpointId);
        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/feed_visualization",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
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
    public function feed_visualization_get(Request $request, string $trafficEndpointId)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'string|min:2|nullable',
            'language' => 'string|min:2|nullable',
            'skip_details' => 'string|min:2|nullable',
            'sub_publisher' => 'string|nullable',
            'funnel' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->feed_visualization_get($trafficEndpointId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/lead_analisis",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="check lead",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function lead_analisis(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leadId' => 'string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->lead_analisis($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/un_payable_leads",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="set un_payable_leads",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function un_payable_leads(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leadIds' => 'string|min:2',
            'type' => 'required|string',
            'reason_change' => 'string|nullable',
            'reason_change2' => 'string|nullable'
            // 'reason_change_crg' => 'string|nullable',
            // 'reason_change_crg2' => 'string|nullable'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->un_payable_leads($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/broker_simulator",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
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
    public function broker_simulator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'string|min:2|nullable',
            'language' => 'string|min:2|nullable',
            'traffic_endpoint' => 'string|min:2|nullable',
            'sub_publisher' =>  'string|nullable',
            'funnel' =>  'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->broker_simulator($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/price/download",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function download_price()
    {
        $content = $this->repository->download_price();
        $filename = 'TrafficEndpointPrice_' . date('Y-m-d') . '.xls';
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/crgdeals/download",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function download_crgdeals()
    {
        $content = $this->repository->download_crgdeals('');
        $filename = 'TrafficEndpointCRG_' . date('Y-m-d') . '.xls';
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/response_tools",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
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
    public function response_tools(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string|min:2',
            'type' => 'required|string|min:2',
            'data' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->response_tools($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/logs",
     *  tags={"traffic_endpoint"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get endpoint log",
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
    public function log(string $trafficEndpointId)
    {
        return response()->json($this->repository->log($trafficEndpointId), 200);
    }
}
