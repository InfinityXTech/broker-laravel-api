<?php

namespace App\Http\Controllers\Brokers;

use App\Helpers\CryptHelper;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;

use App\Helpers\GeneralHelper;
use OpenApi\Annotations as OA;

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Brokers\IBrokerRepository;

/**
 * @OA\PathItem(
 * path="/api/broker"
 * )
 * @OA\Tag(
 *     name="broker",
 *     description="User related operations"
 * )
 */
class BrokerController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_name', 'daily_cr', 'caps', 'all_caps']]);
        // create
        $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['update', 'archive', 'update_caps']]);

        $this->middleware('permissions:brokers[daily_cap]', ['only' => ['caps', 'all_caps']]);

        $this->middleware('permissions:brokers[general]', ['only' => ['update', 'archive']]);

        $this->middleware('permissions:brokers[unpayable_leads]', ['only' => ['un_payable_leads']]);

        $this->middleware('permissions:brokers[conversion_rates]', ['only' => ['conversion_rates']]);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/all",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all brokers",
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
            "partner_name",
            "token",
            "partner_company",
            "today_leads",
            "today_revenue",
            "today_ftd",
            "today_cr",
            "total_leads",
            "total_revenue",
            "total_ftd",
            "total_cr",
            "billing_manual_status",
            "account_manager",
            "created_by",
            "tags"
        ];

        $relations = [
            "account_manager_user:name,account_email",
            "created_by_user:name,account_email",
            "integration_data"
        ];

        return response()->json($this->repository->index($columns, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker",
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
    public function get(string $id)
    {
        $model = $this->repository->findById($id);
        $model->partner_name = CryptHelper::decrypt_broker_name($model->partner_name);
        $model->balance = round($model->balance ?? 0, 0);
        // $model->partner_name = GeneralHelper::broker_name($model);
        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/name/{brokerId}",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker",
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
    public function get_name(string $id)
    {
        $broker = $this->repository->findById($id, ['partner_name', 'token', 'created_by', 'account_manager']);
        // $broker->partner_name = GeneralHelper::broker_name($broker);
        return response()->json($broker, 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/broker/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker"},
     *  summary="Create broker",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'partner_type' => 'required|integer',
            'partner_name' => 'required|string|min:4',
            // 'partner_company' => 'required|string|min:4',
            // 'company_size' => 'required|integer',
            'company_type' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

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
     *  path="/api/broker/update/{brokerId}",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker",
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
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'partner_type' => 'required|integer',
            'partner_name' => 'required|string|min:4',
            // 'partner_company' => 'required|string|min:4',
            'status' => 'required|integer',
            'account_manager' => 'nullable',
            'master_partner' => 'nullable',
            'forbidden_show_traffic_endpoint' => 'nullable|boolean',
            'company_type' => 'required|integer',
            'restricted_endpoints' => 'nullable|array',
            'languages' => 'nullable|array',
            'tags' => 'nullable|array'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['status'] = (string)$payload['status'];

        $payload['partner_name'] = CryptHelper::encrypt($payload['partner_name'] ?? '');
        
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
     *  path="/api/broker/archive/{brokerId}",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker",
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
    public function archive(string $id)
    {
        $result = $this->repository->archive($id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/daily_cr",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker feed_daily_cr",
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
    public function daily_cr(string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/broker_caps/{id}",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker caps",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="capid",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function caps(string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }

    public function all_caps()
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/broker_caps/update{id}",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update broker caps",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="capId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update_caps(string $id)
    {
        return response()->json([
            'success' => false,
            'error' => 'Not Implemented'
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/broker/un_payable_leads",
     *  tags={"broker"},
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
            'broker_reason_change' => 'string|nullable',
            'broker_reason_change2' => 'string|nullable'
            // 'broker_reason_change_crg' => 'string|nullable',
            // 'broker_reason_change_crg2' => 'string|nullable'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->un_payable_leads($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/conversion_rates",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker",
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
    public function conversion_rates(string $id)
    {
        return response()->json($this->repository->conversion_rates($id), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/price/download",
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
        $filename = 'BrokerPrice_' . date('Y-m-d') . '.xls';
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/crgdeals/download",
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
        $filename = 'BrokerCRG_' . date('Y-m-d') . '.xls';
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename);
    }
}
