<?php

namespace App\Http\Controllers\Notifications;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
// use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ApiController;

use Illuminate\Support\Facades\Validator;
use App\Repository\Notifications\INotificationsRepository;
use MongoDB\Operation\Update;

/**
 * @OA\PathItem(
 * path="/api/notifications"
 * )
 * @OA\Tag(
 *     name="notifications",
 *     description="User related operations"
 * )
 */
class NotificationsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(INotificationsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * @OA\Get(
     *  path="/api/notifications",
     *  tags={"notifications"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get Notifications",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function notifications()
    {
        return response()->json($this->repository->notifications(), 200);
    }
}
