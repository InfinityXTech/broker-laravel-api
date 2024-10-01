<?php

namespace App\Http\Controllers\Leads;

use Illuminate\Http\Request;
use App\Helpers\GeneralHelper;
// use Illuminate\Support\Facades\Route;

use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Gate;

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Leads\ILeadsRepository;
use Illuminate\Support\Facades\Log;

/**
 * @OA\PathItem(
 * path="/api/leads"
 * )
 * @OA\Tag(
 *     name="leads",
 *     description="User related operations"
 * )
 */
class LeadsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ILeadsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */

    /**
     * @OA\Put(
     *  path="/leads/test_lead/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function test_lead(string $leadId)
    {
        $model = $this->repository->test_lead($leadId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/leads/alerts",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function listAlerts(Request $request)
    {
        return response()->json($this->repository->listAlerts(), 200);
    }

    /**
     * @OA\Put(
     *  path="/leads/alerts/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function createAlerts(Request $request, string $leadId)
    {
        $data = $request->validate([
            'category' => 'required',
            'execution_at' => 'required'
        ]);

        $model = $this->repository->createAlerts($leadId, $data + ['status' => 1, 'created_by' => auth()->id()]);
        return response()->json($model, 200);
    }

    /**
     * @OA\Delete(
     *  path="/leads/alerts/{alertID}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
     *       @OA\Parameter(
     *          name="alertID",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function deleteAlerts(Request $request, string $alertID)
    {
        $model = $this->repository->deleteAlerts($alertID);
        return response()->json($model, 200);
    }

    /**
     * @OA\Put(
     *  path="/leads/approve/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function approve(string $leadId)
    {
        $model = $this->repository->approve($leadId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Put(
     *  path="/leads/fire_ftd/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function fire_ftd(Request $request, string $leadId)
    {

        $validator = Validator::make($request->all(), [
            'fake_deposit' => 'boolean|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $fake_deposit = true;
        if (Gate::allows('custom:crm[mark_fire_ftd]')) {
            $fake_deposit = ($payload['fake_deposit'] ?? false);
        }
        $model = $this->repository->fireftd($leadId, $fake_deposit);
        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/leads/{leadId}/crg_lead",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function crg_lead(string $leadId)
    {
        $model = $this->repository->get_crg_lead($leadId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/leads/{leadId}/crg_ftd",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function crg_ftd(string $leadId)
    {
        $model = $this->repository->get_crg_ftd($leadId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/leads/mark_crg_lead/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function mark_crg_lead(Request $request, string $leadId)
    {
        $validator = Validator::make($request->all(), [
            'broker_crg_percentage_id' => 'string|nullable',
            'broker_crg_payout' => 'string|nullable',
            'broker_crg_payout_manual' => 'integer|min:0|nullable',

            'broker_reason_change_crg' => 'string|nullable',
            'broker_reason_change_crg2' => 'string|nullable',

            'crg_percentage_id' => 'string|nullable',
            'crg_payout' => 'string|nullable',
            'crg_payout_manual' => 'integer|min:0|nullable',

            'reason_change_crg' => 'string|nullable',
            'reason_change_crg2' => 'string|nullable',

        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->mark_crg_lead($leadId, $payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/leads/change_crg_ftd/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function change_crg_ftd(Request $request, string $leadId)
    {
        $validator = Validator::make($request->all(), [
            'broker_changed_crg_percentage_id' => 'string|nullable',
            'broker_changed_crg_payout' => 'string|nullable',
            'broker_changed_crg_payout_manual' => 'integer|min:0|nullable',

            'changed_crg_percentage_id' => 'string|nullable',
            'changed_crg_payout' => 'string|nullable',
            'changed_crg_payout_manual' => 'integer|min:0|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->change_crg_ftd($leadId, $payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/leads/{leadId}/payout",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function get_payout(string $leadId)
    {
        $model = $this->repository->get_payout($leadId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/leads/update/{leadId}/payout",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
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
    public function update_payout(Request $request, string $leadId)
    {
        $validator = Validator::make($request->all(), [
            'deposit_revenue' => 'required|integer|nullable',
            'cost' => 'required|integer|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->update_payout($leadId, $payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/leads/send_test_lead/data",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function test_lead_data(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'integrationId' => 'string|nullable',
            'brokerId' => 'string|nullable',
            'endpointId' => 'string|nullable',

            'country' => 'string|nullable',
            'funnel_language' => 'string|nullable',

            'first_name' => 'string|nullable',
            'last_name' => 'string|nullable',
            'funnel_lp' => 'string|nullable',
            'sub_publisher' => 'string|nullable',
            'password' => 'string|nullable',

        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->test_lead_data($payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/leads/send_test_lead/send",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function test_lead_send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'integrationId' => 'string|nullable',
            'brokerId' => 'string|nullable',
            'endpointId' => 'string|required',

            // 'traffic_endpoint' => 'string|nullable',
            'first_name' => 'string|required',
            'last_name' => 'string|required',
            'funnel_lp' => 'string|nullable',
            'sub_publisher' => 'string|nullable',
            'country' => 'string|required',
            'funnel_language' => 'string|required',
            'password' => 'string|nullable',
            'email' => 'string|required',
            'phone' => 'string|required',
            'ip' => 'string|required',
            'clicktoken' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->test_lead_send($payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/leads/{leadId}/change_payout_cpl_lead",
     *  tags={"leads"},
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
    public function get_change_payout_cpl_lead(string $leadId)
    {
        $model = $this->repository->get_change_payout_cpl_lead($leadId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/leads/{leadId}/change_payout_cpl_lead",
     *  tags={"leads"},
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
    public function post_change_payout_cpl_lead(Request $request, string $leadId)
    {
        $validator = Validator::make($request->all(), [
            'revenue' => 'nullable|numeric|min:0',
            'Master_brand_cost' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'Mastercost' => 'nullable|numeric|min:0',
            'reason_change' => 'string|nullable',
            'reason_change2' => 'string|nullable',
            'broker_reason_change' => 'string|nullable',
            'broker_reason_change2' => 'string|nullable'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->post_change_payout_cpl_lead($leadId, $payload);
        return response()->json($model, 200);
    }
}
