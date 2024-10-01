<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master/{masterId}/billing/chargeback"
 * )
 * @OA\Tag(
 *     name="master_billing_chargeback",
 *     description="User related operations"
 * )
 */
class MasterBillingChargebackController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMasterBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:masters[active=1]', []);
        // view
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_payment_methods', 'get_payment_requests']]);
        // create
        // $this->middleware('permissions:masters[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:masters[billing]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/chargeback/all",
     *  tags={"master_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all masters billing/chargeback",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $masterId)
    {
        return response()->json($this->repository->feed_chargebacks($masterId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/chargeback/{id}",
     *  tags={"master_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master billing/chargeback",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get(string $masterId, string $id)
    {
        return response()->json($this->repository->get_chargeback($id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/chargeback/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"master_billing_chargeback"},
     *  summary="Create master billing/chargeback",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $masterId)
    {
        $validator = [
            'amount' => 'integer|nullable',
            'payment_method' => 'required|string|min:4',
            'screenshots' => 'required|array',
            'payment_request' => 'nullable|string',
        ];

        if (!empty($request->input('payment_request'))) {
            unset($validator['amount']);
        }

        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['master'] = $masterId;

        $model = $this->repository->create_chargeback($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/master/{masterId}/billing/chargeback/update/{id}",
     *  tags={"master_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master billing/chargeback",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $masterId, string $id)
    {
        $validator = [
            'amount' => 'required|integer',
            'payment_method' => 'required|string|min:4',
            'screenshots' => 'required|array',
            'payment_request' => 'nullable|string',
        ];

        if (!empty($request->input('payment_request'))) {
            unset($validator['amount']);
        }

        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update_chargeback($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/master/{masterId}/billing/chargeback/delete/{id}",
     *  tags={"master_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master billing/chargeback",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function delete(string $masterId, string $id)
    {
        $result = $this->repository->delete_chargeback($id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/chargeback/sprav/payment_methods",
     *  tags={"master_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic master_billing_chargeback",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get_payment_methods(string $masterId)
    {
        return $this->repository->get_payment_methods($masterId);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/chargeback/sprav/payment_requests",
     *  tags={"master_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic master_billing_chargeback",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get_payment_requests(string $masterId)
    {
        return $this->repository->get_payment_requests_for_chargeback($masterId);
    }
}
