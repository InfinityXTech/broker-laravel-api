<?php

namespace App\Http\Controllers\Planning;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Planning\IPlanningRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/planning"
 * )
 * @OA\Tag(
 *     name="planning",
 *     description="User related operations"
 * )
 */
class PlanningController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IPlanningRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:planning[active=1]', []);
        // view
        $this->middleware('permissions:planning[access=all|access=view|access=add|access=edit]', ['only' => ['run', 'countries_and_languages']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/planning",
     *  security={{"bearerAuth":{}}},
     *  tags={"planning"},
     *  summary="Get planning",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country' => 'nullable|string|min:2',
            'language' => 'nullable|string|min:2',
            'desc_language' => 'nullable|string|min:2',
            'regulated' => 'nullable|string',
            'traffic_sources' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->run($payload);

        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/planning/countries_and_languages",
     *  security={{"bearerAuth":{}}},
     *  tags={"planning"},
     *  summary="Get planning",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function countries_and_languages(Request $request)
    {
        $validator = Validator::make($request->all(), []);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->get_countries_and_languages($payload);

        return response()->json($model, 200);
    }
}
