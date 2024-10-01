<?php

namespace App\Http\Controllers\Affiliates;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Affiliates\IAffiliateBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\Affiliates\AffiliateBillingPaymentMethods;
use App\Models\Affiliates\AffiliateBillingPaymentRequests;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/affiliates/{affiliateId}/billing/payment_requests"
 * )
 * @OA\Tag(
 *     name="affiliates_billing_payment_requests",
 *     description="User related operations"
 * )
 */
class AffiliateBillingPaymentRequestsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAffiliateBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_affiliates[active=1]', []);
        // view
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'calculations', 'get_invoice', 'files']]);
        // create
        // $this->middleware('permissions:marketing_affiliates[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_affiliates[access=all|access=edit]', ['only' => ['create', 'pre_create_query', 'update', 'delete', 'approve', 'reject', 'master_approve', 'real_income', 'final_approve', 'final_reject', 'archive']]);

        // $this->middleware('permissions:marketing_affiliates[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/feed",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $affiliateId)
    {
        return response()->json($this->repository->feed_billing_payment_requests($affiliateId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function get(string $affiliateId, $paymentRequestId)
    {
        return response()->json($this->repository->get_billing_payment_requests($affiliateId, $paymentRequestId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/calculations/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function calculations(string $affiliateId, string $paymentRequestId)
    {
        return response()->json($this->repository->get_billing_payment_request_view_calculation($affiliateId, $paymentRequestId), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/crg_details/{crgId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function crg_details(Request $request, string $affiliateId, string $crgId)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->crg_details($affiliateId, $crgId, $payload), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/pre_create_query",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function pre_create_query(Request $request, string $affiliateId)
    {
        $validator = Validator::make($request->all(), [
            'affiliate' => 'required|string',
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

        $result = $this->repository->pre_create_billing_payment_requests($affiliateId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/create",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $affiliateId)
    {
        $validator = [
            'affiliate' => 'required|string',
            'payment_request_type' => 'required|string|min:4',
            'amount' => 'required|integer|min:0',
            'timeframe' => 'required|string|min:4',
            'adjustment_amount_sign' => 'nullable|integer',
            'adjustment_amount_value' => 'required_if:adjustment_amount_sign,1|nullable|integer',
            'adjustment_description' => 'required_if:adjustment_amount_sign,1|nullable|string',
        ];

        $payment_request_type = $request->input('payment_request_type');
        if ($payment_request_type == 'payment') {
            unset($validator['amount']);
        }
        if ($payment_request_type == 'prepayment') {
            unset($validator['timeframe'], $validator['adjustment_amount_sign'], $validator['adjustment_amount_value'], $validator['adjustment_description']);
        }
        $validator = Validator::make($request->all(), $validator);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['adjustment_amount'] = ($payload['adjustment_amount_sign'] ?? 1) * ($payload['adjustment_amount_value'] ?? 0);

        $result = $this->repository->create_billing_payment_requests($affiliateId, $payload);

        return response()->json([
            'success' => !empty($result)
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/invoice/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function get_invoice(string $affiliateId, string $paymentRequestId)
    {
        return $this->repository->get_payment_request_invoice($affiliateId, $paymentRequestId);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/files/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function files(string $affiliateId, $paymentRequestId)
    {
        return response()->json($this->repository->files_billing_payment_requests($affiliateId, $paymentRequestId), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/approve/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function approve(Request $request, string $affiliateId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->approve_billing_payment_requests($affiliateId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/reject/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function reject(Request $request, string $affiliateId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->reject_billing_payment_requests($affiliateId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/master_approve/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function master_approve(Request $request, string $affiliateId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'string|nullable',
            'master_approve_files' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->master_approve_billing_payment_requests($affiliateId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/real_income/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function real_income(Request $request, string $affiliateId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), [
            'real_income' => "integer|nullable",
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->real_income_billing_payment_requests($affiliateId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/final_approve/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function final_approve(Request $request, string $affiliateId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => "string|nullable",
            'date_pay' => "required|nullable",
            'final_approve_files' => 'nullable|array',
            'transaction_id' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['payment_method'])) {
            $payment_method = AffiliateBillingPaymentMethods::findOrFail($payload['payment_method']);
            if (empty($payload['transaction_id'] ?? '') && $payment_method->payment_method == 'crypto' && ($payment_method->currency_crypto_code == 'btc' || $payment_method->currency_crypto_code == 'usdt')) {
                return response()->json([
                    'transaction_id' => ['Transaction ID is required']
                ], 422);
            }
        }

        if (!empty($payload['transaction_id'] ?? '')) {

            $count = AffiliateBillingPaymentRequests::query()
                ->where('affiliate', '=', $affiliateId)
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

        $result = $this->repository->final_approve_billing_payment_requests($affiliateId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Put(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/final_reject/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function final_reject(Request $request, string $affiliateId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->final_reject_billing_payment_requests($affiliateId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/payment_requests/archive/{paymentRequestId}",
     *  tags={"affiliates_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
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
    public function archive(Request $request, string $affiliateId, $paymentRequestId)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->archive_rejected_billing_payment_requests($affiliateId, $paymentRequestId, $payload);

        return response()->json($result, 200);
    }
}
