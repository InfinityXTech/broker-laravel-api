<?php

namespace App\Http\Controllers\Investigate;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Investigate\IInvestigateRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/investigate"
 * )
 * @OA\Tag(
 *     name="investigate",
 *     description="User related operations"
 * )
 */
class InvestigateController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IInvestigateRepository $repository)
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
     *  path="/api/investigate/user/{leadId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"investigate"},
     *  summary="Get investigate",
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
    public function logs(string $leadId)
    {
        $model = $this->repository->logs($leadId);
        return response()->json($model, 200);
    }
}
