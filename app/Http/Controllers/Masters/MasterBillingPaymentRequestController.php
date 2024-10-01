<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\Masters\MasterBillingPaymentMethod;
use App\Models\Masters\MasterBillingPaymentRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master/{masterId}/billing/payment_requests"
 * )
 * @OA\Tag(
 *     name="master_billing_payment_requests",
 *     description="User related operations"
 * )
 */
class MasterBillingPaymentRequestController extends ApiController
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
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'view_calculations', 'get_invoice', 'get_files']]);
        // update
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['create', 'pre_create_query', 'approve', 'master_approve', 'reject', 'fin_approve', 'fin_reject', 'archive', 'real_income']]);

        $this->middleware('permissions:masters[billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/payment_requests/all",
     *  tags={"master_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete master billing/payment_requests",
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
        return response()->json($this->repository->feed_payment_requests($masterId, false), 200);
    }

    public function completed(string $masterId)
    {
        return response()->json($this->repository->feed_payment_requests($masterId, true), 200);
    }

    public function get(string $masterId, string $id)
    {
        return response()->json($this->repository->get_payment_request($id), 200);
    }

    public function view_calculations(string $masterId, string $id)
    {
        return response()->json($this->repository->get_payment_request_calculations($masterId, $id), 200);
    }

    public function get_invoice(string $masterId, string $id)
    {
        return $this->repository->get_payment_request_invoice($masterId, $id);
    }

    public function get_files(string $masterId, string $id)
    {
        return response()->json($this->repository->get_payment_request_files($id), 200);
    }

    public function pre_create_query(Request $request, string $masterId)
    {
        $validator = Validator::make($request->all(), [
            'payment_request_type' => 'required|string',
            'timeframe' => 'required|string',
            'adjustment_amount_sign' => 'nullable|integer',
            'adjustment_amount_value' => 'nullable|integer|min:0',
            'adjustment_description' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['adjustment_amount'] = ($payload['adjustment_amount_sign'] ?? 1) * ($payload['adjustment_amount_value'] ?? 0);

        return response()->json($this->repository->feed_payment_requests_query($masterId, $payload), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/master/{masterId}/billing/payment_requests/select{id}",
     *  tags={"master_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete master billing/payment_requests",
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
    public function create(Request $request, string $masterId)
    {
        $validator = [
            'payment_request_type' => 'required|string|min:4',
            'amount' => 'required_if:payment_request_type,prepayment|nullable|integer|min:0',
            'timeframe' => 'required|string|min:4',
            'adjustment_amount_sign' => 'nullable|integer',
            'adjustment_amount_value' => 'required_if:adjustment_amount_sign,1|nullable|integer',
            'adjustment_description' => 'required_if:adjustment_amount_sign,1|nullable|string',
            'payment_method' => 'required|string',
            'master_approve_files' => 'required|array',
            'proof_screenshots' => 'required|array',
            'proof_description' => 'required|string',
            'leads' => 'nullable|string',
        ];

        $payment_request_type = $request->input('payment_request_type');
        if ($payment_request_type == 'payment') {
            unset($validator['amount']);
        }
        if ($payment_request_type == 'prepayment') {
            unset($validator['adjustment_amount_value']);
            unset($validator['adjustment_description']);
            unset($validator['timeframe'], $validator['adjustment_amount_sign'], $validator['adjustment_amount_value'], $validator['adjustment_description']);
        }

        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['adjustment_amount'] = ($payload['adjustment_amount_sign'] ?? 1) * ($payload['adjustment_amount_value'] ?? 0);

        $result = $this->repository->create_payment_request($masterId, $payload);

        return response()->json($result, 200);
    }

    public function approve(Request $request, string $masterId, string $id)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json([
            'success' => $this->repository->payment_request_approve($masterId, $id, $payload)
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_requests/master_approve/{paymentRequestId}",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="paymentRequestId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function master_approve(Request $request, string $masterId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'string|nullable',
            'master_approve_files' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json([
            'success' => $this->repository->payment_request_master_approve($masterId, $id, $payload)
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_requests/reject/{paymentRequestId}",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="paymentRequestId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function reject(string $masterId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_reject($masterId, $id)
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_requests/fin_approve/{paymentRequestId}",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="paymentRequestId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function fin_approve(Request $request, string $masterId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'final_status_date_pay' => 'required',
            'payment_method' => 'required',
            'final_approve_files' => 'required|array',
            'transaction_id' => 'string|nullable',
            'hash_url' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['payment_method'])) {
            $payment_method = MasterBillingPaymentMethod::findOrFail($payload['payment_method']);
            if (empty($payload['transaction_id'] ?? '') && $payment_method->payment_method == 'crypto' && ($payment_method->currency_crypto_code == 'btc' || $payment_method->currency_crypto_code == 'usdt')) {
                return response()->json([
                    'transaction_id' => ['Transaction ID is required']
                ], 422);
            }
            if (empty($payload['hash_url'] ?? '') && $payment_method->payment_method == 'crypto' && ($payment_method->currency_crypto_code == 'btc' || $payment_method->currency_crypto_code == 'usdt')) {
                return response()->json([
                    'hash_url' => ['Hash Url is required']
                ], 422);
            }
        }

        if (!empty($payload['transaction_id'] ?? '')) {
            $count = MasterBillingPaymentRequest::query()
                ->where('master', '=', $masterId)
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

        if (!empty($payload['hash_url'] ?? '')) {
            $count = MasterBillingPaymentRequest::query()
                ->where('master', '=', $masterId)
                ->where('hash_url', '=', $payload['hash_url'])
                ->where('final_status', '=', 1)
                ->get(['_id'])
                ->count();

            $transaction_id_exists = ($count > 0);
            if ($transaction_id_exists) {
                return response()->json([
                    'hash_url' => ['Hash Url already exists']
                ], 422);
            }
        }

        return response()->json([
            'success' => $this->repository->payment_request_fin_approve($masterId, $id, $payload)
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_requests/fin_reject/{paymentRequestId}",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="paymentRequestId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function fin_reject(string $masterId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_fin_reject($masterId, $id)
        ], 200);
    }

    // public function real_income(Request $request, string $masterId, string $id)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'income' => 'required|integer|min:0',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json($validator->errors(), 422);
    //     }

    //     $payload = $validator->validated();

    //     return response()->json([
    //         'success' => $this->repository->payment_request_real_income($masterId, $id, $payload)
    //     ], 200);
    // }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_requests/archive/{paymentRequestId}",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="paymentRequestId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function archive(string $masterId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_archive($masterId, $id)
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_requests/real_income/{paymentRequestId}",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="paymentRequestId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function real_income(Request $request, string $trafficEndpointId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), [
            'real_income' => "integer|nullable",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->payment_request_real_income($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }
}
