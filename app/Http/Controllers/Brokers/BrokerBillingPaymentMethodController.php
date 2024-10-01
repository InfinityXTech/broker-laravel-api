<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\Brokers\BrokerBillingPaymentMethod;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/billing/payment_methods"
 * )
 * @OA\Tag(
 *     name="broker_billing_payment_methods",
 *     description="User related operations"
 * )
 */
class BrokerBillingPaymentMethodController extends ApiController
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
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['our_index', 'index']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['select', 'our_select']]);

        $this->middleware('permissions:brokers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/our_payment_methods/all",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="get broker billing/payment_methods",
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
    public function our_index(string $brokerId)
    {
        return response()->json($this->repository->feed_our_payment_methods($brokerId), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/our_payment_methods/select{id}",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Select broker billing/our_payment_methods",
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
    public function our_select(string $brokerId, string $id)
    {
        $result = $this->repository->select_our_payment_method($brokerId, $id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/payment_methods/all",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="get broker billing/payment_methods",
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
        return response()->json($this->repository->feed_payment_methods($brokerId), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/billing/payment_methods/select{id}",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Select broker billing/payment_methods",
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
    public function select(string $brokerId, string $id)
    {
        $result = $this->repository->select_payment_method($brokerId, $id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/billing/payment_methods/{id}",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing",
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
    public function get(string $brokerId, string $paymentMethodId)
    {
        $model = BrokerBillingPaymentMethod::findOrFail($paymentMethodId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/billing/payment_methods/create",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing",
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
    public function create(Request $request, string $brokerId)
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

            $response = $this->repository->create_payment_method($brokerId, $payload);
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * @OA\Post(
     *  path="/api/broker/{brokerId}/billing/payment_methods/update/{id}",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing",
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
    public function update(Request $request, string $brokerId, string $id)
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

            $response = $this->repository->update_payment_methods($id, $payload);
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * @OA\Patch(
     *  path="/api/broker/{brokerId}/billing/payment_methods/files/{paymentMethodId}",
     *  tags={"broker_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker billing",
     *       @OA\Parameter(
     *          name="brokerId",
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
    public function files(string $brokerId, string $paymentMethodId)
    {
        return response()->json($this->repository->files_payment_methods($brokerId, $paymentMethodId), 200);
    }
}
