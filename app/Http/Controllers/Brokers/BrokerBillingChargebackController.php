<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use App\Helpers\GeneralHelper;
use OpenApi\Annotations as OA;
// use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;

use App\Models\Brokers\BrokerBillingPaymentMethod;
use App\Repository\Brokers\IBrokerBillingRepository;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/billing/chargeback"
 * )
 * @OA\Tag(
 *     name="broker_billing_chargeback",
 *     description="User related operations"
 * )
 */
class BrokerBillingChargebackController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:brokers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/chargeback/all",
     *  tags={"broker_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all brokers billing/chargeback",
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
    public function index(string $brokerId)
    {
        return response()->json($this->repository->feed_chargebacks($brokerId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/chargeback/{id}",
     *  tags={"broker_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing/chargeback",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function get(string $brokerId, string $id)
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
     *  path="/api/broker/{brokerId}/billing/chargeback/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_billing_chargeback"},
     *  summary="Create broker billing/chargeback",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $brokerId)
    {
        $validator = [
            'amount' => 'required|integer',
            'payment_method' => 'required|string|min:4',
            'screenshots' => 'required|array',
            'payment_request' => 'nullable|string',
            // 'final_approve_files' => 'required|array',
            'proof_screenshots' => 'required|array',
            'proof_description' => 'required:string|min:4'
        ];

        $payload = $request->all();

        if (($payload['payment_request'] ?? '') == 'undefined') {
            unset($payload['payment_request']);
        }

        if (!empty($payload['payment_request'])) {
            unset($validator['amount']);
        }

        $validator = Validator::make($payload, $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['broker'] = $brokerId;

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
     *  path="/api/broker/{brokerId}/billing/chargeback/update/{id}",
     *  tags={"broker_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker billing/chargeback",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function update(Request $request, string $brokerId, string $id)
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
     *  path="/api/{brokerId}/billing/chargeback/delete/{id}",
     *  tags={"broker_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker billing/chargeback",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function delete(string $brokerId, string $id)
    {
        $result = $this->repository->delete_chargeback($id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Post Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/{brokerId}/billing/chargeback/fin_approve/{id}",
     *  tags={"broker_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker billing/chargeback",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function fin_approve(Request $request, string $brokerId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'date_pay' => 'required',
            // 'file' => 'required|array',
            'final_approve_files' => 'required|array',
            'is_request_transaction_id' => 'nullable',
            'transaction_id' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (((bool)($payload['is_request_transaction_id'] ?? false)) == true && empty(($payload['transaction_id'] ?? ''))) {
            return response()->json([
                'transaction_id' => ['Transaction ID is required']
            ], 422);
        }

        if (!empty($payload['transaction_id'] ?? '')) {
            $count = BrokerBillingPaymentMethod::query()
                ->where('type', '=', 'broker')
                ->where('broker', '=', $brokerId)
                ->where('transaction_id', '=', $payload['transaction_id'])
                ->where('final_status', '=', 1)
                ->get(['_id'])
                ->count();

            $transaction_id_exists = ($count > 0);
            if ($transaction_id_exists) {
                return response()->json([
                    'transaction_id' => ['Transaction ID already exists']
                ], 422);
            }
        }

        return response()->json([
            'success' => $this->repository->fin_approve_chargeback($brokerId, $id, $payload)
        ], 200);
    }

    /**
     * Post Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/{brokerId}/billing/chargeback/fin_reject/{id}",
     *  tags={"broker_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put broker billing/chargeback",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function fin_reject(string $brokerId, string $id)
    {
        return response()->json([
            'success' => $this->repository->fin_reject_chargeback($brokerId, $id)
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/chargeback/files/{id}",
     *  tags={"broker_billing_chargeback"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing/chargeback",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function files(string $brokerId, string $id)
    {
        return response()->json($this->repository->files_chargebacks($brokerId, $id), 200);
    }
}
