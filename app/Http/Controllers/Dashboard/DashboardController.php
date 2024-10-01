<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Dashboard\IDashboardRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/dashboard"
 * )
 * @OA\Tag(
 *     name="dashboard",
 *     description="User related operations"
 * )
 */
class DashboardController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IDashboardRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * @OA\Get(
     *  path="/api/dashboard",
     *  tags={"dashboard"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all traffic endpoints",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        return response()->json($this->repository->index(), 200);
    }

}
