<?php

namespace App\Http\Controllers\Billings;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Billings\IBillingsRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/billings"
 * )
 * @OA\Tag(
 *     name="billings",
 *     description="User related operations"
 * )
 */
class BillingsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBillingsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:billings[active=1]', []);
        // view
        $this->middleware('permissions:billings[access=all|access=view|access=add|access=edit]', ['only' => ['overall', 'pending_payments', 'brokers_balances', 'endpoint_balances']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/billings/overall",
     *  security={{"bearerAuth":{}}},
     *  tags={"billings"},
     *  summary="Get billings",
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
     *  path="/api/billings/pending_payments",
     *  security={{"bearerAuth":{}}},
     *  tags={"billings"},
     *  summary="Get billings",
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
     *  path="/api/billings/brokers_balances",
     *  security={{"bearerAuth":{}}},
     *  tags={"billings"},
     *  summary="Get billings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function brokers_balances(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'collection' => 'required|boolean',
        // ]);
        // if ($validator->fails()) {
        //     return response()->json($validator->errors()->toJson(), 400);
        // }

        // $payload = $validator->validated();

        return response()->json($this->repository->brokers_balances([]), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/billings/endpoint_balances",
     *  security={{"bearerAuth":{}}},
     *  tags={"billings"},
     *  summary="Get billings",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function endpoint_balances(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'collection' => 'required|boolean',
        // ]);
        // if ($validator->fails()) {
        //     return response()->json($validator->errors()->toJson(), 400);
        // }

        // $payload = $validator->validated();

        return response()->json($this->repository->endpoint_balances([]), 200);
    }


    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/billings/approved",
     *  security={{"bearerAuth":{}}},
     *  tags={"billings"},
     *  summary="Get billings",
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
