<?php

namespace App\Http\Controllers\Affiliates;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Affiliates\IAffiliateBillingRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/affiliates/{affiliateId}/billing/entities"
 * )
 * @OA\Tag(
 *     name="affiliates_billing_entities",
 *     description="User related operations"
 * )
 */
class AffiliateBillingEntitiesController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IAffiliateBillingRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_affiliates[active=1]', []);
        // view
        $this->middleware('permissions:marketing_affiliates[access=all|access=view|access=add|access=edit]', ['only' => ['index', 'get']]);
        // create
        // $this->middleware('permissions:marketing_affiliates[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:marketing_affiliates[access=all|access=edit]', ['only' => ['create', 'update', 'delete']]);

        // $this->middleware('permissions:marketing_affiliates[is_billing]', []);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/entities/all",
     *  tags={"affiliates_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(string $affiliateId)
    {
        return response()->json($this->repository->feed_billing_entities($affiliateId), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/affiliates/{affiliateId}/billing/entities/create",
     *  tags={"affiliates_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function create(Request $request, string $affiliateId)
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
            'files' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->create_billing_entities($affiliateId, $payload), 200);
    }

    /**
     * @OA\Put(
     *  path="/api/affiliates/{affiliateId}/billing/entities/update/(entityId)",
     *  tags={"affiliates_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="entityId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function update(Request $request, string $affiliateId, string $entityId)
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
            'files' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        return response()->json($this->repository->update_billing_entities($affiliateId, $entityId, $payload), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/affiliates/{affiliateId}/billing/entities/{entityId}",
     *  tags={"affiliates_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="entityId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get(string $affiliateId, string $entityId)
    {
        return response()->json($this->repository->get_billing_entities($affiliateId, $entityId), 200);
    }

    /**
     * @OA\Delete(
     *  path="/api/affiliates/{affiliateId}/billing/entities/delete/{entityId}",
     *  tags={"affiliates_billing_entities"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get affiliate affiliate_billing",
     *       @OA\Parameter(
     *          name="affiliateId",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="entityId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function remove(string $affiliateId, string $entityId)
    {
        return response()->json($this->repository->remove_billing_entities($affiliateId, $entityId), 200);
    }
}
