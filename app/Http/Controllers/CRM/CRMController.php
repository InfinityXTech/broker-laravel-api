<?php

namespace App\Http\Controllers\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\CRM\ICRMRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/crm"
 * )
 * @OA\Tag(
 *     name="crm",
 *     description="User related operations"
 * )
 */
class CRMController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ICRMRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:crm[active=1]', []);
        // view
        $this->middleware('permissions:crm[access=all|access=view|access=add|access=edit]', ['only' => ['leads', 'ftd', 'mismatch', 'status_lead_history']]);
        // $this->middleware('permissions:crm_all_leads[access=all|access=view|access=add|access=edit]', ['only' => ['leads', 'status_lead_history']]);
        // $this->middleware('permissions:crm_depositors[access=all|access=view|access=add|access=edit]', ['only' => ['ftd', 'status_lead_history']]);
        // $this->middleware('permissions:crm_mistmaths[access=all|access=view|access=add|access=edit]', ['only' => ['mismatch', 'status_lead_history']]);

        $this->middleware('permissions:crm[depositors=1]', ['only' => ['ftd']]);
        $this->middleware('permissions:crm[leads=1]', ['only' => ['leads']]);
        $this->middleware('permissions:crm[mismatch=1]', ['only' => ['mismatch']]);

        // update
        $this->middleware('permissions:crm[access=all|access=edit]', ['only' => ['reject', 'approve', 'resync']]);
    }

    /**
     * @OA\Post(
     *  path="/api/crm/leads",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function leads(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string|min:2|nullable',
            'traffic_endpoint' => 'string|min:2|nullable',
            'broker' => 'string|min:2|nullable',
            'broker_lead_id' => 'string|min:2|nullable',
            'click_id' => 'string|min:2|nullable',
            'country' => 'string|min:2|nullable',
            'email' => 'string|min:2|nullable|email',
            'first_name' => 'string|min:2|nullable',
            'language' => 'string|min:2|nullable',
            'last_name' => 'string|min:2|nullable',
            'lead_id' => 'string|min:2|nullable',
            'phone' => 'string|min:2|nullable',
            'status' => 'string|min:2|nullable',
            'broker_status' => 'string|min:2|nullable',
            'crg' => 'string|nullable',
            'broker_crg' => 'string|nullable',
            'sub_publisher' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->all_leads($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/crm/ftd",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all deposits",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function ftd(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string|min:2|nullable',
            'traffic_endpoint' => 'string|min:2|nullable',
            'broker' => 'string|min:2|nullable',
            'broker_lead_id' => 'string|min:2|nullable',
            'click_id' => 'string|min:2|nullable',
            'country' => 'string|min:2|nullable',
            'email' => 'string|min:2|nullable',
            'first_name' => 'string|min:2|nullable',
            'language' => 'string|min:2|nullable',
            'last_name' => 'string|min:2|nullable',
            'lead_id' => 'string|min:2|nullable',
            'phone' => 'string|min:2|nullable',
            'status' => 'string|min:2|nullable',
            'broker_status' => 'string|min:2|nullable',
            'crg' => 'string|nullable',
            'broker_crg' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->deposits($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/crm/mismatch",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get mismatch",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function mismatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string|min:2|nullable',
            'traffic_endpoint' => 'string|min:2|nullable',
            'broker' => 'string|min:2|nullable',
            'broker_lead_id' => 'string|min:2|nullable',
            'click_id' => 'string|min:2|nullable',
            'country' => 'string|min:2|nullable',
            'email' => 'string|min:2|nullable',
            'first_name' => 'string|min:2|nullable',
            'language' => 'string|min:2|nullable',
            'last_name' => 'string|min:2|nullable',
            'lead_id' => 'string|min:2|nullable',
            'phone' => 'string|min:2|nullable',
            'broker_status' => 'string|min:2|nullable',
            'status' => 'string|min:2|nullable',
            'crg' => 'string|nullable',
            'broker_crg' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->mismatch($payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/crm/status_lead_history/{leadId}",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get log leads status",
     *       @OA\Parameter(
     *          name="leadId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function status_lead_history(string $leadId)
    {
        $model = $this->repository->status_lead_history($leadId);

        return response()->json($model, 200);
    }

    /**
     * @OA\Put(
     *  path="/api/crm/reject",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get log leads status",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function reject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'partner_type' => 'required|integer',
            // 'company_type' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->reject($payload);

        return response()->json($model, 200);
    }

    /**
     * @OA\Put(
     *  path="/api/crm/approve",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get log leads status",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function approve(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'partner_type' => 'required|integer',
            // 'company_type' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->approve($payload);

        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/crm/resync/get",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get log leads status",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get_resync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // $ids = explode(',', $payload['ids']);

        $model = $this->repository->get_resync($payload['ids']);

        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/crm/resync",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get log leads status",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function resync(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_lps' => 'required|string',
            'endpoint' => 'string|nullable',
            'interval' => 'int|nullable|max:200',
            'integrations' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['interval'])) {
            $payload['interval'] = intval($payload['interval']);
        }

        $model = $this->repository->resync($payload);

        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/crm/download_recalculation_changes_log",
     *  tags={"crm"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get log leads status",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function download_recalculation_changes_log(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $filename = 'Recalculation_Changes_' . date('Y-m-d') . '.xls';
        $content = $this->repository->download_recalculation_changes_log($payload);
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename);
    }

}
