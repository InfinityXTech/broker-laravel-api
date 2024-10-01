<?php

namespace App\Http\Controllers\MarketingBillings;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\MarketingBillings\IMarketingBillingsRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/marketing_billings"
 * )
 * @OA\Tag(
 *     name="marketing_billings",
 *     description="User related operations"
 * )
 */
class MarketingBillingsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMarketingBillingsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_billings[active=1]', []);
        // view
        $this->middleware('permissions:marketing_billings[access=all|access=view|access=add|access=edit]', ['only' => ['overall', 'pending_payments', 'advertisers_balances', 'affiliates_balances']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_billings/overall",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_billings"},
     *  summary="Get marketing_billings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function overall()
    {
        return response()->json($this->repository->overall(), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_billings/pending_payments",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_billings"},
     *  summary="Get marketing_billings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function pending_payments()
    {
        return response()->json($this->repository->pending_payments(), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_billings/advertisers_balances",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_billings"},
     *  summary="Get marketing_billings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function advertisers_balances(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'collection' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->advertisers_balances($payload), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_billings/affiliates_balances",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_billings"},
     *  summary="Get marketing_billings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function affiliates_balances(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'collection' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->affiliates_balances($payload), 200);
    }


    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_billings/approved",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_billings"},
     *  summary="Get marketing_billings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function approved(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string|min:2',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->approved($payload), 200);
    }
}
