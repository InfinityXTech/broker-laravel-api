<?php

namespace App\Http\Controllers\MarketingSuite;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\MarketingSuite\IMarketingSuiteRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/traffic_endpoint/marketing_suite"
 * )
 * @OA\Tag(
 *     name="marketing_suite",
 *     description="User related operations"
 * )
 */
class MarketingSuiteController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMarketingSuiteRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        $this->middleware('permissions:traffic_endpoint[active=1]', []);
        $this->middleware('permissions:traffic_endpoint[marketing_suite]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/marketing_suite/all",
     *  tags={"marketing_suite"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get marketing_suite",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        $relations = [
            "created_by_user:name,account_email"
        ];
        return response()->json($this->repository->index(['*'], $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/marketing_suite/{marketingSuiteId}",
     *  tags={"marketing_suite"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get marketing_suite",
     *       @OA\Parameter(
     *          name="marketingSuiteId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function get(string $id)
    {
        return response()->json($this->repository->get($id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_suite/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_suite"},
     *  summary="Create marketing_suite",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2',
            'description' => 'string|nullable',
            'countries' => 'nullable|array',
            'languages' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->create($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/marketing_suite/update/{marketingSuiteId}",
     *  tags={"marketing_suite"},
     *  security={{"bearerAuth":{}}},
     *  summary="Update marketing_suite",
     *       @OA\Parameter(
     *          name="marketingSuiteId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer',
            'name' => 'required|string|min:2',
            'description' => 'string|nullable',
            'countries' => 'nullable|array',
            'languages' => 'nullable|array',
            'vertical' => 'required|string',
            'platform' => 'nullable|array',
            'conversion_type' => 'string|nullable',
            'ristrictions' => 'array|nullable',
            'private' => 'boolean',

            'logo_image' => 'nullable|array',
            'screenshot_image' => 'nullable|array',

            'promoted_offer' => 'nullable|string',
            'exclusive_offer' => 'nullable|string',
            'category' => 'nullable|string',
            'price' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['promoted_offer'] = $payload['promoted_offer'] ?? '0';
        $payload['exclusive_offer'] = $payload['exclusive_offer'] ?? '0';

        $result = $this->repository->update($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/marketing_suite/delete/{marketingSuiteId}",
     *  tags={"marketing_suite"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete marketing_suite",
     *       @OA\Parameter(
     *          name="marketingSuiteId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function delete(string $id)
    {
        $result = $this->repository->delete($id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/marketing_suite/get_tracking_link/{marketingSuiteId}",
     *  tags={"marketing_suite"},
     *  security={{"bearerAuth":{}}},
     *  summary="Delete marketing_suite",
     *       @OA\Parameter(
     *          name="marketingSuiteId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function get_tracking_link(string $id)
    {
        $result = $this->repository->get_tracking_link($id);

        return response()->json($result, 200);
    }
}
