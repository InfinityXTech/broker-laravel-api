<?php

namespace App\Http\Controllers\TrafficEndpoints;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\TrafficEndpoints\ITrafficEndpointBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\TrafficEndpoints\TrafficEndpointBillingPaymentMethods;
use App\Models\TrafficEndpoints\TrafficEndpointBillingPaymentRequests;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing_payment_requests",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingPaymentRequestsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ITrafficEndpointBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:traffic_endpoint[active=1]', []);
        // view
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'calculations', 'get_invoice', 'files']]);
        // create
        // $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'pre_create_query', 'update', 'delete', 'approve', 'reject', 'master_approve', 'real_income', 'final_approve', 'final_reject', 'archive']]);

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/feed",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
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
    public function index(string $trafficEndpointId)
    {
        return response()->json($this->repository->feed_billing_payment_requests($trafficEndpointId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/{paymentRequestId}",
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
    public function get(string $trafficEndpointId, $paymentRequestId)
    {
        return response()->json($this->repository->get_billing_payment_requests($trafficEndpointId, $paymentRequestId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/calculations/{paymentRequestId}",
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
    public function calculations(string $trafficEndpointId, string $paymentRequestId)
    {
        return response()->json($this->repository->get_billing_payment_request_view_calculation($trafficEndpointId, $paymentRequestId), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/crg_details/{crgId}",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="crgId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function crg_details(Request $request, string $trafficEndpointId, string $crgId)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->crg_details($trafficEndpointId, $crgId, $payload), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/pre_create_query",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
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
    public function pre_create_query(Request $request, string $trafficEndpointId)
    {
        $validator = Validator::make($request->all(), [
            'TrafficEndpoint' => 'required|string',
            'payment_request_type' => 'required|string',
            'timeframe' => 'required|string|min:2',
            'amount' => 'integer|nullable',
            'adjustment_amount' => 'integer|nullable',
            'adjustment_description' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->pre_create_billing_payment_requests($trafficEndpointId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/create",
     *  tags={"traffic_endpoint_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
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
    public function create(Request $request, string $trafficEndpointId)
    {
        $validator = [
            'TrafficEndpoint' => 'required|string',
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

        $result = $this->repository->create_billing_payment_requests($trafficEndpointId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/invoice/{paymentRequestId}",
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
    public function get_invoice(string $trafficEndpointId, string $paymentRequestId)
    {
        return $this->repository->get_payment_request_invoice($trafficEndpointId, $paymentRequestId);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/files/{paymentRequestId}",
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
    public function files(string $trafficEndpointId, $paymentRequestId)
    {
        return response()->json($this->repository->files_billing_payment_requests($trafficEndpointId, $paymentRequestId), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/approve/{paymentRequestId}",
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
    public function approve(Request $request, string $trafficEndpointId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->approve_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/reject/{paymentRequestId}",
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
    public function reject(Request $request, string $trafficEndpointId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->reject_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/master_approve/{paymentRequestId}",
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
    public function master_approve(Request $request, string $trafficEndpointId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'string|nullable',
            'master_approve_files' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->master_approve_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/real_income/{paymentRequestId}",
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

        $result = $this->repository->real_income_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/final_approve/{paymentRequestId}",
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
    public function final_approve(Request $request, string $trafficEndpointId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => "string|nullable",
            'date_pay' => "required|nullable",
            'final_approve_files' => 'nullable|array',
            'transaction_id' => 'string|nullable',
            'hash_url' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // if (!empty($payload['payment_method'])) {
        //     $payment_method = TrafficEndpointBillingPaymentMethods::findOrFail($payload['payment_method']);
        //     if (empty($payload['transaction_id'] ?? '') && $payment_method->payment_method == 'crypto' && ($payment_method->currency_crypto_code == 'btc' || $payment_method->currency_crypto_code == 'usdt')) {
        //         return response()->json([
        //             'transaction_id' => ['Transaction ID is required']
        //         ], 422);
        //     }
        // }

        $payment_request = TrafficEndpointBillingPaymentRequests::findOrFail($paymentRequestId);
        if (!empty($payment_request->payment_method)) {
            $payment_method = TrafficEndpointBillingPaymentMethods::findOrFail($payload['payment_method']);
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

            $count = TrafficEndpointBillingPaymentRequests::query()
                ->where('endpoint', '=', $trafficEndpointId)
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

            $count = TrafficEndpointBillingPaymentRequests::query()
                ->where('endpoint', '=', $trafficEndpointId)
                ->where('hash_url', '=', $payload['hash_url'])
                ->where('final_status', '=', 1)
                ->get(['_id'])
                ->count();

            $transaction_id_exists = ($count > 0);
            if ($transaction_id_exists) {
                return response()->json([
                    'transaction_id' => ['Hash Url already exists']
                ], 422);
            }
        }

        $result = $this->repository->final_approve_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Put(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/final_reject/{paymentRequestId}",
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
    public function final_reject(Request $request, string $trafficEndpointId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->final_reject_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_requests/archive/{paymentRequestId}",
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
    public function archive(Request $request, string $trafficEndpointId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->archive_rejected_billing_payment_requests($trafficEndpointId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }
}
