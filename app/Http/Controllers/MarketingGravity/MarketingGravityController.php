<?php

namespace App\Http\Controllers\MarketingGravity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\MarketingGravity\IMarketingGravityRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/marketing_gravity"
 * )
 * @OA\Tag(
 *     name="gravity",
 *     description="User related operations"
 * )
 */
class MarketingGravityController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMarketingGravityRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_gravity[active=1]', []);
        // view
        $this->middleware('permissions:marketing_gravity[access=all|access=view|access=add|access=edit]', ['only' => ['run', 'logs']]);
        // update
        $this->middleware('permissions:marketing_gravity[access=all|access=edit]', ['only' => ['reject', 'approve']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/marketing_gravity/leads/{type}",
     *  security={{"bearerAuth":{}}},
     *  tags={"gravity"},
     *  summary="Get gravity",
     *       @OA\Parameter(
     *          name="type",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run(string $type)
    {
        $allows = ['auto' => 1, 'manual' => 2, 'tech' => 3, 'high' => 4, 'financial' => 5];

        if (!array_key_exists($type, $allows)) {
            return response()->json(['success' => false, 'error' => 'Access Denied'], 422);
        }

        $model = $this->repository->leads($allows[$type]);

        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/marketing_gravity/leads/title/{type}",
     *  security={{"bearerAuth":{}}},
     *  tags={"gravity"},
     *  summary="Get gravity",
     *       @OA\Parameter(
     *          name="type",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run_title(string $type)
    {
        $allows = [
            'auto' => ['key' => 1, 'title' => 'Auto Approve'],
            'manual' => ['key' => 2, 'title' => 'Manual Approve'],
            'tech' => ['key' => 3, 'title' => 'Tech Issue'],
            'high' => ['key' => 4, 'title' => 'High Risk FTD’s'],
            'financial' => ['key' => 5, 'title' => 'Financial Risk']
        ];

        if (!array_key_exists($type, $allows)) {
            return response()->json(['success' => false, 'error' => 'Access Denied'], 422);
        }

        $model = $this->repository->leads($allows[$type]['key']);

        $title = $allows[$type]['title'] . ' (' . count($model) . ')';

        return response()->json($title, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/marketing_gravity/log/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"gravity"},
     *  summary="Get gravity",
     *       @OA\Parameter(
     *          name="leadId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function logs()
    {
        $model = $this->repository->change_log();

        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/marketing_gravity/reject/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"gravity"},
     *  summary="Get gravity",
     *       @OA\Parameter(
     *          name="leadId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function reject(string $leadId)
    {
        $model = $this->repository->reject($leadId);
        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/marketing_gravity/approve/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"gravity"},
     *  summary="Get gravity",
     *       @OA\Parameter(
     *          name="leadId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function approve(string $leadId)
    {
        $model = $this->repository->approve($leadId);
        return response()->json($model, 200);
    }
}
