<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Settings\ISettingsRepository;
use App\Http\Controllers\ApiController;

use Illuminate\Support\Facades\Gate;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/settings"
 * )
 * @OA\Tag(
 *     name="settings",
 *     description="Settings related operations"
 * )
 */
class SettingsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ISettingsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
        $this->middleware('roles:admin', ['only' => []]);
    }

    /**
     * @OA\Get(
     *  path="/api/settings",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all settingss",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get()
    {
        return response()->json($this->repository->get(), 200);
    }



    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/settings/set",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put settings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function set(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cdn_url' => 'string|nullable',
            'marketing_suite_domain_url' => 'string|nullable',
            'marketing_suite_tracking_url' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->set($payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/settings/payment_methods",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="get payment_methods",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function payment_methods()
    {
        return response()->json($this->repository->feed_payment_methods(), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/settings/payment_methods/create",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="get payment_methods",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create_payment_methods(Request $request)
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

        // if ($payload['payment_method'] == 'crypto') {
        //     $payload['currency_code'] = null;
        // } else {
        //     $payload['currency_crypto_code'] = null;
        // }

        // $result = $this->repository->create_payment_methods($payload);

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

            $response = $this->repository->create_payment_methods($payload);
            return response()->json($response, 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => [$ex->getMessage()]], 422);
        }
    }

    /**
     * @OA\Get(
     *  path="/api/settings/payment_companies",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="get payment_companies",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function payment_companies()
    {
        return response()->json($this->repository->feed_payment_companies(), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/settings/payment_companies/create",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="get payment_companies",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create_payment_companies(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organization_name' => 'required|string',
            'organization_address' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->create_payment_companies($payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Post(
     *  path="/api/settings/subscribers/update",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="get subscribers",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update_subscribers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'finance_email_subscribers' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update_subscribers($payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/settings/subscribers",
     *  tags={"settings"},
     *  security={{"bearerAuth":{}}},
     *  summary="get subscribers",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function get_subscribers()
    {
        return response()->json($this->repository->get_subscribers(), 200);
    }
}
