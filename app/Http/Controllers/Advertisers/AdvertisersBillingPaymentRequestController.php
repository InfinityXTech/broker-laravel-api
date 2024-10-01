<?php

namespace App\Http\Controllers\Advertisers;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Advertisers\IAdvertisersBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\Advertisers\MarketingAdvertiserBillingPaymentRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/advertisers/{advertiserId}/billing/payment_requests"
 * )
 * @OA\Tag(
 *     name="advertisers_billing_payment_requests",
 *     description="User related operations"
 * )
 */
class AdvertisersBillingPaymentRequestController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAdvertisersBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_advertisers[active=1]', []);
        // view
        $this->middleware('permissions:marketing_advertisers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'completed', 'view_calculations', 'get_invoice', 'get_files']]);
        // create
        // $this->middleware('permissions:marketing_advertisers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_advertisers[access=all|access=edit]', ['only' => ['create', 'pre_create_query', 'approve', 'change', 'reject', 'fin_approve', 'fin_reject', 'real_income', 'archive']]);

        // $this->middleware('permissions:marketing_advertisers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/payment_requests/all",
     *  tags={"advertisers_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete advertiser billing/payment_requests",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function index(string $advertiserId)
    {
        return response()->json($this->repository->feed_payment_requests($advertiserId, false), 200);
    }

    public function completed(string $advertiserId)
    {
        return response()->json($this->repository->feed_payment_requests($advertiserId, true), 200);
    }

    public function get(string $advertiserId, string $id)
    {
        return response()->json($this->repository->get_payment_request($id), 200);
    }

    public function view_calculations(string $advertiserId, string $id)
    {
        return response()->json($this->repository->get_payment_request_calculations($advertiserId, $id), 200);
    }

    public function get_invoice(string $advertiserId, string $id)
    {
        return $this->repository->get_payment_request_invoice($advertiserId, $id);
    }

    public function get_files(string $advertiserId, string $id)
    {
        return response()->json($this->repository->get_payment_request_files($id), 200);
    }

    public function pre_create_query(Request $request, string $advertiserId)
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

        return response()->json($this->repository->feed_payment_requests_query($advertiserId, $payload), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/advertisers/{advertiserId}/billing/payment_requests/select{id}",
     *  tags={"advertisers_billing_payment_requests"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete advertiser billing/payment_requests",
     *       @OA\Parameter(
     *          name="advertiserId",
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
    public function create(Request $request, string $advertiserId)
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

        $result = $this->repository->create_payment_request($advertiserId, $payload);

        return response()->json([
            'success' => !empty($result)
        ], 200);
    }

    public function approve(Request $request, string $advertiserId, string $id)
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
            'success' => $this->repository->payment_request_approve($advertiserId, $id, $payload)
        ], 200);
    }

    public function change(Request $request, string $advertiserId, string $id)
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
            'success' => $this->repository->payment_request_change($advertiserId, $id, $payload)
        ], 200);
    }

    public function reject(string $advertiserId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_reject($advertiserId, $id)
        ], 200);
    }

    public function fin_approve(Request $request, string $advertiserId, string $id)
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
            $count = MarketingAdvertiserBillingPaymentRequest::query()
                ->where('advertiser', '=', $advertiserId)
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
            'success' => $this->repository->payment_request_fin_approve($advertiserId, $id, $payload)
        ], 200);
    }

    public function fin_reject(string $advertiserId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_fin_reject($advertiserId, $id)
        ], 200);
    }

    public function real_income(Request $request, string $advertiserId, string $id)
    {
        $validator = Validator::make($request->all(), [
            'income' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json([
            'success' => $this->repository->payment_request_real_income($advertiserId, $id, $payload)
        ], 200);
    }

    public function archive(string $advertiserId, string $id)
    {
        return response()->json([
            'success' => $this->repository->payment_request_archive($advertiserId, $id)
        ], 200);
    }
}
