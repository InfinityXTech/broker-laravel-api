<?php

namespace App\Http\Controllers\MarketingInvestigate;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\MarketingInvestigate\IMarketingInvestigateRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/marketing_investigate"
 * )
 * @OA\Tag(
 *     name="marketing_investigate",
 *     description="User related operations"
 * )
 */
class MarketingInvestigateController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMarketingInvestigateRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/marketing_investigate/{event}/{clickId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_investigate"},
     *  summary="Get marketing_investigate",
     *       @OA\Parameter(
     *          name="event",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="clickId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function logs(string $event, string $clickId)
    {
        $model = $this->repository->logs($clickId);
        return response()->json($model, 200);
    }
}
