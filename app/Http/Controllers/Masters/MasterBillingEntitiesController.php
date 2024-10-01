<?php

namespace App\Http\Controllers\Masters;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Masters\IMasterBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/master/{masterId}/billing/entities"
 * )
 * @OA\Tag(
 *     name="master_billing_entities",
 *     description="User related operations"
 * )
 */
class MasterBillingEntitiesController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMasterBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:masters[active=1]', []);
        // view
        $this->middleware('permissions:masters[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:masters[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:masters[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        $this->middleware('permissions:masters[billing]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/entities/all",
     *  tags={"master_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all masters billing/entities",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $masterId)
    {
        return response()->json($this->repository->feed_entities($masterId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/master/{masterId}/billing/entities/{id}",
     *  tags={"master_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get master billing/entities",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
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
    public function get(string $masterId, string $id)
    {
        return response()->json($this->repository->get_entity($id), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/master/{masterId}/billing/entities/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"master_billing_entities"},
     *  summary="Create master billing/entities",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $masterId)
    {
        $validator = Validator::make($request->all(), [
            'company_legal_name' => 'required|string|min:4',
            'country_code' => 'required|string|min:2',
            'region' => 'required|string|min:4',
            'city' => 'required|string|min:2',
            'zip_code' => 'required|string|min:2',
            'currency_code' => 'required|string|min:3',
            'vat_id' => 'nullable|string|min:4',
            'registration_number' => 'required|string|min:4',
            'file' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['master'] = $masterId;

        $model = $this->repository->create_entity($payload);

        return response()->json($model, 200);
    }

    /**
     * Update Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/master/{masterId}/billing/entities/update/{id}",
     *  tags={"master_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master billing/entities",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
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
    public function update(Request $request, string $masterId, string $id)
    {
        $validator = Validator::make($request->input(), [
            'company_legal_name' => 'required|string|min:4',
            'country_code' => 'required|string|min:2',
            'region' => 'required|string|min:4',
            'city' => 'required|string|min:2',
            'zip_code' => 'required|string|min:2',
            'currency_code' => 'required|string|min:3',
            'vat_id' => 'nullable|string|min:4',
            'registration_number' => 'required|string|min:4',
            'file' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->update_entity($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * Delete Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Delete(
     *  path="/api/{masterId}/billing/entities/delete/{id}",
     *  tags={"master_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put master billing/entities",
     *       @OA\Parameter(
     *          name="masterId",
     *          in="path",
     *          required=true, 
     *      ),
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
    public function delete(string $masterId, string $id)
    {
        $result = $this->repository->delete_entity($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
