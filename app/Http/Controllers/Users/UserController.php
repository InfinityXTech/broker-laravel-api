<?php

namespace App\Http\Controllers\Users;

use App\Classes\Access;
use App\Helpers\BucketHelper;
use Illuminate\Http\Request;
use App\Helpers\ClientHelper;
use App\Helpers\GeneralHelper;
// use Illuminate\Support\Facades\Route;

use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Gate;

use App\Http\Controllers\ApiController;

use App\Repository\Users\IUserRepository;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\PathItem(
 * path="/api/user"
 * )
 * @OA\Tag(
 *     name="user",
 *     description="User related operations"
 * )
 */
class UserController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IUserRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
        $this->middleware('roles:admin', []); //'index', 'get', 'get_permissions', 'update_permissions', 'create', 'update', 'archive', 'reset_password']]);

        // examples
        // $this->middleware('roles:admin|account_manager')->except(['index']);
        // $this->middleware('permissions:traffic_endpoint[active=1]', ['only' => ['index', 'get', 'create', 'update', 'archive']]);
        // $this->middleware('permissions:traffic_endpoint[access=all]', ['only' => ['index', 'get', 'create', 'update', 'archive']]);
        // $this->middleware('permissions:traffic_endpoint[is_only_assigned=1]', ['only' => ['index', 'get', 'create', 'update', 'archive']]);

        // if(Gate::allows('role:admin') || Gate::allows('role:account_manager')){
        //     // do something
        // }
        // if(Gate::allows('traffic_endpoint[is_only_assigned=1]')){
        //     // do something
        // }
        // if(Gate::denies(Gate::allows('role:account_manager')){
        //     // do something
        // }
    }

    /**
     * @OA\Get(
     *  path="/api/user/all",
     *  tags={"user"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all users",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        $columns = [];
        return response()->json($this->repository->index($columns), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/user/{userId}",
     *  tags={"user"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get user",
     *       @OA\Parameter(
     *          name="userId",
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
        return response()->json($this->repository->findById($id), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/user/{userId}/permissions",
     *  tags={"user"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get user",
     *       @OA\Parameter(
     *          name="userId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get_permissions(string $id)
    {
        $user = $this->repository->findById($id);

        Access::attach_custom_access($user, false);

        return response()->json([
            'name' => $user->name,
            'permissions' => $user->permissions
        ], 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/user/{userId}/permissions/update/",
     *  tags={"user"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put user",
     *       @OA\Parameter(
     *          name="userId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update_permissions(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $clientId = ClientHelper::clientId();
        // $env = config('app.env');
        // if (
        //     $clientId != '633c07530b1a55629a3b0a1d' &&  // roibees
        //     $env != 'local' &&
        //     isset($payload['permissions']['traffic_endpoint']['is_scrub_permission']) &&
        //     ((bool)($payload['permissions']['traffic_endpoint']['is_scrub_permission'] ?? 0))
        // ) {
        //     $payload['permissions']['traffic_endpoint']['is_scrub_permission'] = false;
        // }

        $result = $this->repository->update_permissions($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/user/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"user"},
     *  summary="Create user",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer',
            'name' => 'required|string',
            'account_email' => 'required|email|string|min:4',
            'skype' => 'string|min:2',
            'roles' => 'required|array|nullable',
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
     *  path="/api/user/update/{userId}",
     *  tags={"user"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put user",
     *       @OA\Parameter(
     *          name="userId",
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
            'name' => 'required|string',
            'account_email' => 'required|email|string|min:4',
            'skype' => 'string|min:2|nullable',
            'roles' => 'required|array|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Patch(
     *  path="/api/user/archive/{userId}",
     *  tags={"user"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put user",
     *       @OA\Parameter(
     *          name="userId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function archive(string $id)
    {
        $result = $this->repository->archive($id);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Archive Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Patch(
     *  path="/api/user/reset_password/{userId}",
     *  tags={"user"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put user",
     *       @OA\Parameter(
     *          name="userId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function reset_password(string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'string|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->reset_password($id, $payload['password'] ?? '');

        return response()->json([
            'success' => true,
            'password' => $result
        ], 200);
    }
}
