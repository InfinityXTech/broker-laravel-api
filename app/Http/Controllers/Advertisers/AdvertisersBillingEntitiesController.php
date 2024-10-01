<?php

namespace App\Http\Controllers\Advertisers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Advertisers\IAdvertisersBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/advertisers/{advertiserId}/billing/entities"
 * )
 * @OA\Tag(
 *     name="advertisers_billing_entities",
 *     description="User related operations"
 * )
 */
class AdvertisersBillingEntitiesController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAdvertisersBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_advertisers[active=1]', []);
        // view
        $this->middleware('permissions:marketing_advertisers[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:marketing_advertisers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_advertisers[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        // $this->middleware('permissions:marketing_advertisers[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/entities/all",
     *  tags={"advertisers_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all advertisers billing/entities",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $advertiserId)
    {
        return response()->json($this->repository->feed_entities($advertiserId), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/advertisers/{advertiserId}/billing/entities/{id}",
     *  tags={"advertisers_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get advertiser billing/entities",
     *       @OA\Parameter(
     *          name="advertiserId",
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
    public function get(string $advertiserId, string $id)
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
     *  path="/api/advertisers/{advertiserId}/billing/entities/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"advertisers_billing_entities"},
     *  summary="Create advertiser billing/entities",
     *       @OA\Parameter(
     *          name="advertiserId",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $advertiserId)
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
        $payload['advertiser'] = $advertiserId;

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
     *  path="/api/advertisers/{advertiserId}/billing/entities/update/{id}",
     *  tags={"advertisers_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put advertiser billing/entities",
     *       @OA\Parameter(
     *          name="advertiserId",
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
    public function update(Request $request, string $advertiserId, string $id)
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
     *  path="/api/{advertiserId}/billing/entities/delete/{id}",
     *  tags={"advertisers_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Put advertiser billing/entities",
     *       @OA\Parameter(
     *          name="advertiserId",
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
    public function delete(string $advertiserId, string $id)
    {
        $result = $this->repository->delete_entity($id);

        return response()->json([
            'success' => $result
        ], 200);
    }
}
