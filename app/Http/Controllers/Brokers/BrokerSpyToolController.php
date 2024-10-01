<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Repository\Brokers\IBrokerSpyToolRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker_spy_tool"
 * )
 * @OA\Tag(
 *     name="broker_spy_tool",
 *     description="User related operations"
 * )
 */
class BrokerSpyToolController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerSpyToolRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);

        $this->middleware('permissions:brokers[spy_tool]', []);

    }

    /**
     * @OA\Get(
     *  path="/api/broker_spy_tool/{leadId}",
     *  tags={"broker_spy_tool"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker_spy_tool",
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
    public function brokers_and_integrations(string $leadId)
    {
        return response()->json($this->repository->get_brokers_and_integrations($leadId), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/broker_spy_tool/run",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_spy_tool"},
     *  summary="Create broker_spy_tool",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|string|min:4',
            'broker' => 'required|string|min:4',
            'integration' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->run($payload);

        return response()->json($model, 200);
    }

}
