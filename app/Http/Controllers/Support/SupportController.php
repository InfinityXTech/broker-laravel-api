<?php

namespace App\Http\Controllers\Support;

use Exception;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;

use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
use OpenApi\Annotations as OA;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Support\ISupportRepository;

/**
 * @OA\PathItem(
 * path="/api/support"
 * )
 * @OA\Tag(
 *     name="support",
 *     description="User related operations"
 * )
 */
class SupportController extends ApiController
{
    private $repository;

    // public function boot()
    // {
    // }

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ISupportRepository $repository)
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
     *  path="/api/support/all",
     *  security={{"bearerAuth":{}}},
     *  tags={"support"},
     *  summary="Get support",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        $relations = [
            "created_by_user:name,account_email",
            "assigned_to_user:name,account_email",
            "broker_user:partner_name,token,created_by,account_manager",
            "endpoint_user:token"
        ];

        return response()->json($this->repository->index(['*'], $relations), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/support/page/{page}",
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
    public function page(Request $request, int $page = 1)
    {

        $validator = Validator::make($request->all(), [
            'broker' => 'string|nullable',
            'traffic_endpoint' => 'string|nullable',
            'status' => 'array|nullable',
            'user' => 'string|nullable',
            'timeframe' => 'string|nullable',
            'search' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $relations = [
            "created_by_user:name,account_email",
            "broker_user:partner_name,token,created_by,account_manager",
            "endpoint_user:token"
            // "assigned_to_user:name,account_email",
        ];

        return response()->json($this->repository->page($page, $payload, ['*'], $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/support/{supportId}",
     *  tags={"support"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get support",
     *       @OA\Parameter(
     *          name="supportId",
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
        $relations = [
            "created_by_user:name,account_email",
            // "assigned_to_user:name,account_email",
            "comments:*"
        ];

        return response()->json($this->repository->get($id, ['*'], $relations), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/support/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"support"},
     *  summary="Create support",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $payload = $request->all();

        Validator::extend(
            'broker_check_required',
            function ($attribute, $value, $parameters) use ($payload) {
                if ((int)$payload['integration'] == 1 && (int)$payload['type'] == 1 && empty($value)) {
                    return false;
                }
                if ((int)$payload['type'] == 5 && empty($value)) {
                    return false;
                }
                return true;
            },
            'Broker is required'
        );

        Validator::extend(
            'traffic_endpoint_check_required',
            function ($attribute, $value, $parameters) use ($payload) {
                if ((int)$payload['integration'] == 2 && (int)$payload['type'] == 1 && empty($value)) {
                    return false;
                }
                if ((int)$payload['type'] == 5 && empty($value)) {
                    return false;
                }
                return true;
            },
            'Traffic Endpoint is required'
        );

        $validator = Validator::make($payload, [
            'integration' => 'required|integer|min:1',
            'type' => 'required|integer|min:1',
            'broker' => 'string|min:6|nullable|broker_check_required',
            'traffic_endpoint' => 'string|min:6|nullable|traffic_endpoint_check_required',
            'broker_api_documentstion' => 'string|min:6|nullable',
            'subject' => 'required_if:type,5|nullable|string',
            'note' => 'string|nullable',
            'files' => 'array|nullable',
            'assigned_to' => 'array|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $type = (int)$payload['type'] ?? 0;

        if ($type == 1 || $type == 5) {
            $integration = (int)$payload['integration'] ?? 0;
            if ($integration == 1 && empty($payload['broker'])) {
                return response()->json(['broker' => ['Broker can\'t be empty']], 422);
            }
            if ($integration == 2 && empty($payload['traffic_endpoint'])) {
                return response()->json(['traffic_endpoint' => ['Traffic Endpoint can\'t be empty']], 422);
            }
        }

        if ($type == 1 || $type == 5) {
            $clientId = ClientHelper::clientId();

            if (empty($clientId)) {
                $variables = config('variables') ?? [];
            } else {
                $variables = config('clients.' . $clientId . '.variables') ?? [];
            }

            if ($type == 5) {
                $financial_support = $variables['support_users_financial'] ?? [];
                if (count($financial_support) > 0) {
                    $payload['assigned_to'] = $financial_support;
                }
            }
            if ($type == 1) {
                $financial_support = $variables['support_users_request_integration'] ?? [];
                if (count($financial_support) > 0) {
                    $payload['assigned_to'] = $financial_support;
                }
            }
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
     *  path="/api/support/update/{supportId}",
     *  tags={"support"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put support",
     *       @OA\Parameter(
     *          name="supportId",
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
            'files' => 'array|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {
            $result = $this->repository->update($id, $payload);
        } catch (Exception $ex) {
            return response()->json([
                'status' => [$ex->getMessage()]
            ], 422);
        }

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
     *  path="/api/support/delete/{supportId}",
     *  tags={"support"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put support",
     *       @OA\Parameter(
     *          name="supportId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function delete(string $id)
    {
        $result = $this->repository->delete($id);

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
     * @OA\Post(
     *  path="/api/support/{supportId}/send_comment",
     *  tags={"support"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put support",
     *       @OA\Parameter(
     *          name="supportId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function send_comment(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->send_comment($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
