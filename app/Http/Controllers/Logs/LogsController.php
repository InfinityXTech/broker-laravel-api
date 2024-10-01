<?php

namespace App\Http\Controllers\Logs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Logs\ILogsRepository;
use App\Http\Controllers\ApiController;

use Illuminate\Support\Facades\Gate;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/logs"
 * )
 * @OA\Tag(
 *     name="logs",
 *     description="Logs related operations"
 * )
 */
class LogsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ILogsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
        $this->middleware('roles:admin', ['only' => []]);
    }

    /**
     * @OA\Get(
     *  path="/api/logs/{page}",
     *  tags={"logs"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all logss",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function logs($page = 1)
    {
        return response()->json($this->repository->get_log($page), 200);
    }
}
