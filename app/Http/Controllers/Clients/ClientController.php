<?php

namespace App\Http\Controllers\Clients;

use Illuminate\Http\Request;

use OpenApi\Annotations as OA;

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Clients\IClientRepository;

/**
 * @OA\PathItem(
 * path="/api/clients"
 * )
 * @OA\Tag(
 *     name="client",
 *     description="User related operations"
 * )
 */
class ClientController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IClientRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // // active
        // $this->middleware('permissions:clients[active=1]', []);
        // // view
        // $this->middleware('permissions:clients[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get', 'get_name', 'daily_cr', 'caps', 'all_caps']]);
        // // create
        // $this->middleware('permissions:clients[access=all|access=add]', ['only' => ['create']]);
        // // update
        // $this->middleware('permissions:clients[access=all|access=edit]', ['only' => ['update', 'archive', 'update_caps']]);
    }

    /**
     * @OA\Get(
     *  path="/api/clients/all",
     *  tags={"client"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all clients",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index()
    {
        $columns = ['*'];
        $relations = [];
        return response()->json($this->repository->index($columns, $relations), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/clients/{clientId}",
     *  tags={"client"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get client",
     *       @OA\Parameter(
     *          name="clientId",
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
        $model = $this->repository->get($id);
        return response()->json($model, 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/clients/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"client"},
     *  summary="Create client",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nickname' => 'required|string|min:4',
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
     * @OA\Post(
     *  path="/api/clients/update/{clientId}",
     *  tags={"client"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put client",
     *       @OA\Parameter(
     *          name="clientId",
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
            "api_domain" => 'required|string|min:4|url',
            "crm_domain" => 'required|string|min:4|url',
            "nickname" => 'required|string|min:4',

            // "login_background" => 'required|string|min:4',
            // "logo_url_big" => 'required|string|min:4',
            // "logo_url_small" => 'required|string|min:4',
            // "favicon_url" => 'required|string|min:4',

            "login_background_file" => 'array|nullable',
            "logo_big_file" => 'array|nullable',
            "logo_small_file" => 'array|nullable',
            "favicon_file" => 'array|nullable',

            "partner_domain" => 'string|nullable|url',
            "partner_api_documentation" => 'string|nullable|url',
            
            // "partner_login_background" => 'string|nullable',
            // "partner_logo_url_big" => 'string|nullable',
            // "partner_logo_url_small" => 'string|nullable',
            // "partner_favicon_url" => 'string|nullable',

            "partner_login_background_file" => 'array|nullable',
            "partner_logo_big_file" => 'array|nullable',
            "partner_logo_small_file" => 'array|nullable',
            "partner_favicon_file" => 'array|nullable',

            "master_domain" => 'string|nullable|url',
            
            // "master_login_background" => 'string|nullable',
            // "master_logo_url_big" => 'string|nullable',
            // "master_logo_url_small" => 'string|nullable',
            // "master_favicon_url" => 'string|nullable',

            "master_login_background_file" => 'array|nullable',
            "master_logo_big_file" => 'array|nullable',
            "master_logo_small_file" => 'array|nullable',
            "master_favicon_file" => 'array|nullable',

            "marketing_affiliates_api_domain" => 'string|nullable|url',
            "marketing_affiliates_domain" => 'string|nullable|url',
            "marketing_tracking_domain" => 'string|nullable|url',
        
            "redirect_domain" => 'string|nullable|url',
            "serving_domain" => 'string|nullable|url',
            "status" => 'boolean|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['status'] = (bool)$payload['status'];

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
     *  path="/api/clients/archive/{clientId}",
     *  tags={"client"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put client",
     *       @OA\Parameter(
     *          name="clientId",
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
}
