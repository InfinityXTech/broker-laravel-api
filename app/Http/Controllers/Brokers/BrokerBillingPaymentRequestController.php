<?php

namespace App\Http\Controllers\Brokers;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\Brokers\BrokerBillingPaymentRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/billing/payment_requests"
 * )
 * @OA\Tag(
 *     name="broker_billing_payment_requests",
 *     description="User related operations"
 * )
 */
class BrokerBillingPaymentRequestController extends ApiController
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
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'completed', 'view_calculations', 'get_invoice', 'get_files']]);
        // create
        // $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['create', 'pre_create_query', 'approve', 'change', 'reject', 'fin_approve', 'fin_reject', 'real_income', 'archive']]);

        $this->middleware('permissions:brokers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/payment_requests/all",
     *  tags={"broker_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete broker billing/payment_requests",
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
        return response()->json($this->repository->feed_payment_requests($brokerId, false), 200);
    }

    public function completed(string $brokerId)
    {
        return response()->json($this->repository->feed_payment_requests($brokerId, true), 200);
    }

    public function get(string $brokerId, string $id)
    {
        return response()->json($this->repository->get_payment_request($id), 200);
    }

    public function view_calculations(string $brokerId, string $id)
    {
        return response()->json($this->repository->get_payment_request_calculations($brokerId, $id), 200);
    }

    public function get_invoice(string $brokerId, string $id)
    {
        return $this->repository->get_payment_request_invoice($brokerId, $id);
    }

    public function get_files(string $brokerId, string $id)
    {
        return response()->json($this->repository->get_payment_request_files($id), 200);
    }

    public function pre_create_query(Request $request, string $brokerId)
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

        return response()->json($this->repository->feed_payment_requests_query($brokerId, $payload), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/payment_requests/select{id}",
     *  tags={"broker_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete broker billing/payment_requests",
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
    public function create(Request $request, string $brokerId)
    {
        $validator = [
            'payment_request_type' => 'required|string|min:4',
            'amount' => 'required|integer|min:0',
            'timeframe' => 'required|string|min:4',
            'adjustment_amount_sign' => 'nullable|integer',
            'adjustment_amount_value' => 'required_if:adjustment_amount_sign,1|nullable|integer',
            'adjustment_description' => 'required_if:adjustment_amount_sign,1|nullable|string',
        ];
        if ($request->input('payment_request_type') == 'payment') {
            unset($validator['amount']);
        }
        if ($request->input('payment_request_type') == 'prepayment') {
            unset($validator['timeframe'], $validator['adjustment_amount_sign'], $validator['adjustment_amount_value'], $validator['adjustment_description']);
        }
        $validator = Validator::make($request->all(), $validator);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['adjustment_amount'] = ($payload['adjustment_amount_sign'] ?? 1) * ($payload['adjustment_amount_value'] ?? 0);

        $result = $this->repository->create_payment_request($brokerId, $payload);

        return response()->json([
            'success' => !empty($result)
        ], 200);
    }

    public function approve(Request $request, string $brokerId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'billing_from' => 'required|string|min:4',
            'billing_entity' => 'required|string|min:4',
            'payment_method' => 'required|string|min:4',
            'payment_fee' => 'nullable|numeric|min:0|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json([
            'success' => $this->repository->payment_request_approve($brokerId, $id, $payload)
        ], 200);
    }

    public function change(Request $request, string $brokerId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'billing_from' => 'required|string|min:4',
            'payment_method' => 'required|string|min:4',
            'payment_fee' => 'nullable|numeric|min:0|max:5',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json([
            'success' => $this->repository->payment_request_change($brokerId, $id, $payload)
        ], 200);
    }

    public function reject(string $brokerId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_reject($brokerId, $id)
        ], 200);
    }

    public function fin_approve(Request $request, string $brokerId, string $id)
    {
        // $payload = $request->all();
        // $payload['transaction_id'] ??= '';

        // Validator::extend('transaction_id_required', function ($attribute, $value, $parameters) use ($payload) {
        //     if ((bool)($payload['is_request_transaction_id'] ?? false) == true && empty($value)) {
        //         return false;
        //     }
        //     return true;
        // }, 'Transaction ID is required');

        $validator = Validator::make($request->all(), [
            'date_pay' => 'required',
            'file' => 'required|array',
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
            $count = BrokerBillingPaymentRequest::query()
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
            'success' => $this->repository->payment_request_fin_approve($brokerId, $id, $payload)
        ], 200);
    }

    public function fin_reject(string $brokerId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_fin_reject($brokerId, $id)
        ], 200);
    }

    public function real_income(Request $request, string $brokerId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'income' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json([
            'success' => $this->repository->payment_request_real_income($brokerId, $id, $payload)
        ], 200);
    }

    public function archive(string $brokerId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_archive($brokerId, $id)
        ], 200);
    }
}
