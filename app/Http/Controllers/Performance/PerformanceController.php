<?php

namespace App\Http\Controllers\Performance;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Performance\IPerformanceRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/performance"
 * )
 * @OA\Tag(
 *     name="performance",
 *     description="User related operations"
 * )
 */
class PerformanceController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IPerformanceRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:performance[active=1]', []);
        // view
        $this->middleware('permissions:performance[access=all|access=view|access=add|access=edit]', ['only' => ['general', 'traffic_endpoints', 'brokers', 'deep_dive']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/performance/general",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function general(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->general($payload), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/performance/traffic_endpoints",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function traffic_endpoints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'endpointId' => 'string|nullable',
            'country_code' => 'string|nullable',
            'language_code' => 'string|nullable',
            'timeframe' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->traffic_endpoints($payload), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/performance/brokers",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function brokers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brokerId' => 'string|nullable',
            'country_code' => 'string|nullable',
            'language_code' => 'string|nullable',
            'timeframe' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->brokers($payload), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/performance/vendors",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function vendors(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'apivendorId' => 'string|nullable',
            'country_code' => 'string|nullable',
            'language_code' => 'string|nullable',
            'timeframe' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->vendors($payload), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/performance/deep_dive",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function deep_dive(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brokerId' => 'string|nullable',
            'endpointId' => 'string|nullable',
            'apivendorId' => 'string|nullable',
            'country_code' => 'string|nullable',
            'language_code' => 'string|nullable',
            'timeframe' => 'required|string',
            'error_type' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->deep_dive($payload), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/performance/download",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brokerId' => 'string|nullable',
            'endpointId' => 'string|nullable',
            'apivendorId' => 'string|nullable',
            'country_code' => 'string|nullable',
            'timeframe' => 'required|string',
            'error_type' => 'string|nullable',
            'error_message' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return $this->repository->download($payload);
    }
}
