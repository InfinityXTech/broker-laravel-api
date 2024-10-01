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
 * path="/api/performance/settings/broker_statuses"
 * )
 * @OA\Tag(
 *     name="performance",
 *     description="User related operations"
 * )
 */
class PerformanceSettingsController extends ApiController
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
     * @OA\Get(
     *  path="/api/performance/settings/broker_statuses/all",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function all()
    {
        return response()->json($this->repository->settings_broker_statuses_all(), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/performance/settings/broker_statuses/get/{id}",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Get performance",
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
    public function get(string $id)
    {
        return response()->json($this->repository->settings_broker_statuses_get($id), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/performance/settings/broker_statuses/delete",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Delete performance",
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
    public function delete(string $id)
    {
        return response()->json($this->repository->settings_broker_statuses_delete($id), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/performance/settings/broker_statuses/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Save performance",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'string|required',
            'category' => 'string|required',
            'priority' => 'string|required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->settings_broker_statuses_create($payload), 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/performance/settings/broker_statuses/update/{id}",
     *  security={{"bearerAuth":{}}},
     *  tags={"performance"},
     *  summary="Save performance",
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
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'string|required',
            'category' => 'string|required',
            'priority' => 'string|required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->settings_broker_statuses_update($id, $payload), 200);
    }

}
