<?php

namespace App\Http\Controllers\LeadsReview;

use Illuminate\Http\Request;
use App\Helpers\GeneralHelper;
// use Illuminate\Support\Facades\Route;

use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Gate;

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\LeadsReview\ILeadsReviewRepository;

/**
 * @OA\PathItem(
 * path="/api/leads"
 * )
 * @OA\Tag(
 *     name="leads",
 *     description="User related operations"
 * )
 */
class LeadsReviewController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ILeadsReviewRepository $repository)
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
     * @OA\Post(
     *  path="/leads_review/all",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string',
            'review_status' => 'nullable|array',
            'traffic_endpoint' => 'nullable|string',
            'broker' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $relations = ['broker_data:partner_name,token,created_by,account_manager', 'endpoint_data:token', 'leads_review_tickets_count'];
        $model = $this->repository->index(['_id', 'Timestamp', 'brokerId', 'TrafficEndpoint', 'redirect_url', 'status', 'review_status', 'country'], $relations, $payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Put(
     *  path="/leads_review/checked/{leadId}",
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
    public function checked(string $leadId)
    {
        $model = $this->repository->checked($leadId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/leads_review/tickets/create/{leadId}",
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
    public function create_ticket(Request $request, string $leadId)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'string|nullable',
            'files' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->create_ticket($leadId, $payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/leads_review/tickets/update/{ticketId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
     *       @OA\Parameter(
     *          name="ticketId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update_ticket(Request $request, string $ticketId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer',
            'files' => 'array|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['status'] = (int)$payload['status'];

        $model = $this->repository->update_ticket($ticketId, $payload);
        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/leads_review/tickets",
     *  security={{"bearerAuth":{}}},
     *  tags={"support"},
     *  summary="Get support",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index_ticket()
    {
        $relations = [
            "created_by_user:name,account_email"
        ];

        return response()->json($this->repository->index_ticket(['*'], $relations), 200);
    }

    /**
     * @OA\Post(
     *  path="/leads_review/tickets/page/{page}",
     *  security={{"bearerAuth":{}}},
     *  tags={"support"},
     *  summary="Get support",
     *       @OA\Parameter(
     *          name="page",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function page_ticket(Request $request, int $page = 1)
    {

        $validator = Validator::make($request->all(), [
            'status' => 'array|nullable',
            'search' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $relations = [
            "created_by_user:name,account_email",
        ];

        return response()->json($this->repository->page_ticket($page, $payload, ['*'], $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/leads_review/tickets/{ticketId}",
     *  tags={"support"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get support",
     *       @OA\Parameter(
     *          name="ticketId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get_ticket(string $id)
    {
        $relations = [
            "created_by_user:name,account_email",
            "lead_data:brokerId,TrafficEndpoint,country,language,email,redirect_url,broker_lead_id"
        ];

        return response()->json($this->repository->get_ticket($id, ['*'], $relations), 200);
    }
}
