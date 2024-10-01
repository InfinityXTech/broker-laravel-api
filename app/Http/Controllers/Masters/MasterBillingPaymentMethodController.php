<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\Masters\MasterBillingPaymentMethod;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master/{masterId}/billing/payment_methods"
 * )
 * @OA\Tag(
 *     name="master_billing_payment_methods",
 *     description="User related operations"
 * )
 */
class MasterBillingPaymentMethodController extends ApiController
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
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // update
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['select', 'create']]);

        $this->middleware('permissions:masters[billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/payment_methods/all",
     *  tags={"master_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete master billing/payment_methods",
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
        return response()->json($this->repository->feed_payment_methods($masterId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/payment_methods/{id}",
     *  tags={"master_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master billing",
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
    public function get(string $masterId, string $paymentMethodId)
    {
        $model = MasterBillingPaymentMethod::findOrFail($paymentMethodId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Put(
     *  path="/api/master/{masterId}/billing/payment_methods/select{id}",
     *  tags={"master_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete master billing/payment_methods",
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
    public function select(string $masterId, string $id)
    {
        $result = $this->repository->select_payment_method($masterId, $id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_methods/create",
     *  tags={"master_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete master billing/payment_methods",
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
    public function create(Request $request, string $masterId)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'bank_name' => 'string|nullable',
            'swift' => 'string|nullable',
            'account_name' => 'string|nullable',
            'account_number' => 'string|nullable',
            'currency_code' => 'string|nullable',
            'currency_crypto_code' => 'string|nullable',
            'currency_crypto_wallet_type' => 'string|nullable',
            'wallet' => 'string|nullable',
            'wallet2' => 'string|nullable',
            'notes' => 'string|nullable',
            'files' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {

            if ($payload['payment_method'] == 'crypto') {
                if (empty($payload['wallet'])) {
                    return response()->json(['wallet' => ['Wallet can\'t be empty']], 422);
                }
                if (strpos($payload['wallet'], ' ') !== false) {
                    return response()->json(['wallet' => ['Wallet can\'t have space']], 422);
                }
            }

            if ($payload['payment_method'] != 'crypto' && $payload['currency_crypto_code'] != 'usdt') {
                if (isset($payload['currency_crypto_wallet_type'])) {
                    unset($payload['currency_crypto_wallet_type']);
                }
            }

            $response = $this->repository->create_billing_payment_methods($masterId, $payload);
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/payment_methods/update/{id}",
     *  tags={"master_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master billing",
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
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'bank_name' => 'string|nullable',
            'swift' => 'string|nullable',
            'account_name' => 'string|nullable',
            'account_number' => 'string|nullable',
            'currency_code' => 'string|nullable',
            'currency_crypto_code' => 'string|nullable',
            'currency_crypto_wallet_type' => 'string|nullable',
            'wallet' => 'string|nullable',
            'wallet2' => 'string|nullable',
            'notes' => 'string|nullable',
            'files' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        try {

            if ($payload['payment_method'] == 'crypto') {
                if (empty($payload['wallet'])) {
                    return response()->json(['wallet' => ['Wallet can\'t be empty']], 422);
                }
                if (strpos($payload['wallet'], ' ') !== false) {
                    return response()->json(['wallet' => ['Wallet can\'t have space']], 422);
                }
            }
             
            if ($payload['payment_method'] != 'crypto' && $payload['currency_crypto_code'] != 'usdt') {
                if (isset($payload['currency_crypto_wallet_type'])) {
                    unset($payload['currency_crypto_wallet_type']);
                }
            }

            $response = $this->repository->update_billing_payment_methods($masterId, $id, $payload);
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * @OA\Patch(
     *  path="/api/master/{masterId}/billing/payment_methods/files/{paymentMethodId}",
     *  tags={"master_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="paymentMethodId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function files(string $masterId, $paymentMethodId)
    {
        return response()->json($this->repository->files_billing_payment_methods($masterId, $paymentMethodId), 200);
    }
}
