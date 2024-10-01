<?php

namespace App\Http\Controllers\TrafficEndpoints;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\TrafficEndpoints\ITrafficEndpointBillingRepository;
use App\Http\Controllers\ApiController;
use App\Models\TrafficEndpoints\TrafficEndpointBillingPaymentMethods;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_methods"
 * )
 * @OA\Tag(
 *     name="traffic_endpoint_billing_payment_methods",
 *     description="User related operations"
 * )
 */
class TrafficEndpointBillingPaymentMethodsController extends ApiController
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
        $this->middleware('permissions:traffic_endpoint[access=all|access=view|access=add|access=edit]', ['only' => ['all']]);
        // create
        // $this->middleware('permissions:traffic_endpoint[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:traffic_endpoint[access=all|access=edit]', ['only' => ['create', 'select']]);

        $this->middleware('permissions:traffic_endpoint[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_methods/all",
     *  tags={"traffic_endpoint_billing_payment_methods"},
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
    public function all(string $trafficEndpointId)
    {
        return response()->json($this->repository->feed_billing_payment_methods($trafficEndpointId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_methods/{id}",
     *  tags={"traffic_endpoint_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function get(string $trafficEndpointId, string $paymentMethodId)
    {
        $model = TrafficEndpointBillingPaymentMethods::findOrFail($paymentMethodId);
        return response()->json($model, 200);
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_methods/create",
     *  tags={"traffic_endpoint_billing_payment_methods"},
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

            $response = $this->repository->create_billing_payment_methods($trafficEndpointId, $payload);
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * @OA\Post(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_methods/update/{id}",
     *  tags={"traffic_endpoint_billing_payment_methods"},
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
    public function update(Request $request, string $trafficEndpointId, string $id)
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

            $response = $this->repository->update_billing_payment_methods($trafficEndpointId, $id, $payload);
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * @OA\Patch(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_methods/select/{paymentMethodId}",
     *  tags={"traffic_endpoint_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function select(string $trafficEndpointId, string $paymentMethodId)
    {
        return response()->json($this->repository->active_billing_payment_methods($trafficEndpointId, $paymentMethodId), 200);
    }

    /**
     * @OA\Patch(
     *  path="/api/traffic_endpoint/{trafficEndpointId}/billing/payment_methods/files/{paymentMethodId}",
     *  tags={"traffic_endpoint_billing_payment_methods"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic endpoint_billing",
     *       @OA\Parameter(
     *          name="trafficEndpointId",
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
    public function files(string $trafficEndpointId, $paymentMethodId)
    {
        return response()->json($this->repository->files_billing_payment_methods($trafficEndpointId, $paymentMethodId), 200);
    }
}
