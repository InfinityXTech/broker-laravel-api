<?php

namespace App\Http\Controllers\Brokers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Brokers\IBrokerIntegrationRepository;
use App\Http\Controllers\ApiController;
use App\Repository\Brokers\IBrokerCapsRepository;
use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/broker/{brokerId}/integrations"
 * )
 * @OA\Tag(
 *     name="broker_integrations",
 *     description="User related operations"
 * )
 */
class BrokerCapsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IBrokerCapsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:brokers[active=1]', []);
        // view
        $this->middleware('permissions:brokers[access=all|access=view|access=add|access=edit]', ['only' => ['get', 'all_caps', 'cap_countries', 'logs']]);
        // create
        // $this->middleware('permissions:brokers[access=all|access=add]', ['only' => ['create']]);
        // update
        $this->middleware('permissions:brokers[access=all|access=edit]', ['only' => ['create', 'enable', 'update']]);

        $this->middleware('permissions:brokers[daily_cap]', ['only' => ['get', 'all_caps', 'cap_countries', 'logs']]);
        $this->middleware('permissions:brokers[access=all]', ['only' => ['create', 'enable', 'update']]);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/broker_caps",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function all_caps_get($brokerId = '')
    {
        return response()->json($this->repository->index(['broker_id' => $brokerId]), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/broker/broker_caps",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function all_caps_post(Request $request, $brokerId = '')
    {
        $validator = Validator::make($request->all(), [
            'broker_id' => 'nullable|string',
            'integration_id' => 'nullable|string',
            'country_code' => 'nullable|string',
            'language_code' => 'nullable|string',
            'cap_type' => 'nullable|string',
            'enable_traffic' => 'nullable|boolean',
            'weekends' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        if ($brokerId) {
            $payload['broker_id'] = $brokerId;
        }
        return response()->json($this->repository->index($payload), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/broker_caps/dictionaries",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function all_caps_dictionaries($brokerId = '')
    {
        $brokers = [];
        $integrations = [];
        $countries = [];
        $languages = [];
        foreach ($this->repository->index(['broker_id' => $brokerId]) as $data) {
            $brokers[$data['broker']['_id']] = $data['broker']['name'];
            $integrations[$data['integration']['_id']] = $data['integration']['name'];
            $countries[$data['country']['code']] = $data['country']['name'];
            foreach ($data['languages'] as $lang) {
                $languages[$lang['code']] = $lang['name'];
            }
        }
        asort($brokers);
        asort($integrations);
        asort($countries);
        asort($languages);
        return response()->json([
            'brokers' => $brokers,
            'integrations' => $integrations,
            'countries' => $countries,
            'languages' => $languages,
        ], 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/broker_caps/countries",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function cap_countries($brokerId = '')
    {
        $content = $this->repository->cap_countries($brokerId);
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, 'cap_countries.csv');
    }

    /**
     * @OA\Get(
     *  path="/api/broker/{brokerId}/broker_caps/logs/{id}",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function logs(string $id)
    {
        return response()->json($this->repository->logs($id), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/broker_caps/available_endpoints/{id}",
     *  tags={"broker_integrations"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker integrations",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function available_endpoints(string $id)
    {
        return response()->json($this->repository->available_endpoints($id), 200);
    }

    /**
     * @OA\Post(
     *  path="/api/broker/broker_caps/create",
     *  security={{"bearerAuth":{}}},
     *  tags={"broker_payouts"},
     *  summary="Create broker payout",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function create(string $brokerId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string',
            'integration' => 'required|string',
            'cap_type' => 'required|string',
            'blocked_funnels' => 'nullable|array',
            'blocked_funnels_type' => "nullable|string",
            'blocked_schedule' => 'nullable|array',
            'daily_cap' => 'required|integer',
            'endpoint_dailycaps' => 'nullable|array',
            'language_code' => 'required|array',
            'note' => 'nullable|string',
            'restrict_endpoints' => 'nullable|array',
            'restrict_type' => 'nullable|string',
            'priority' => 'nullable|integer',
            'endpoint_priorities' => 'array|nullable'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();
        $payload['enable_traffic'] = false;
        $payload['broker'] = $brokerId;

        if (count($payload['restrict_endpoints'] ?? []) == 0) {
            $payload['restrict_type'] = null;
        }

        if (count($payload['blocked_funnels'] ?? []) == 0) {
            $payload['blocked_funnels_type'] = null;
        }

        $payload['period_type'] = 'D';

        $model = $this->repository->create($payload);

        return response()->json($model, 200);
    }

    /**
     * @OA\Get(
     *  path="/api/broker/broker_caps/{id}",
     *  tags={"broker"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get broker",
     *       @OA\Parameter(
     *          name="brokerId",
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
        $model = $this->repository->findById($id, ['*'], [], ['broker_name']);
        if (count($model->blocked_funnels ?? []) == 0) {
            $model->blocked_funnels_type = null;
        }
        if (count($model->restrict_endpoints ?? []) == 0) {
            $model->restrict_type = null;
        }
        return response()->json($model, 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/broker_caps/enable/{id}",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Toggle enabled status",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="payoutId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function enable(string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enable_traffic' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = $this->repository->force_update($id, $payload);

        return response()->json([
            'success' => $result
        ], 200);
    }

    /**
     * @OA\Put(
     *  path="/api/broker/{brokerId}/update/{id}",
     *  tags={"broker_payouts"},
     *  security={{"bearerAuth":{}}},
     *  summary="Toggle enabled status",
     *       @OA\Parameter(
     *          name="brokerId",
     *          in="path",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="payoutId",
     *          in="path",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function update(string $id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'blocked_funnels' => 'nullable|array',
            'blocked_funnels_type' => "nullable|string",
            'blocked_schedule' => 'nullable|array',
            'daily_cap' => 'required|integer',
            'endpoint_dailycaps' => 'nullable|array',
            'language_code' => 'required|array',
            'note' => 'nullable|string',
            'restrict_endpoints' => 'nullable|array',
            'restrict_type' => 'nullable|string',
            'priority' => 'nullable|integer',
            'endpoint_priorities' => 'array|nullable'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        if (
            (count($payload['blocked_funnels'] ?? []) > 0) &&
            (count($payload['restrict_endpoints'] ?? []) == 0 || ($payload['restrict_type'] ?? '') == 'black')
        ) {
            return response()->json([
                'restrict_endpoints' => ['if you have chosen "Funnel Restrictions", you must chose the white list of
                "Endpoint Restrictions"']
            ], 422);
        }

        if (count($payload['restrict_endpoints'] ?? []) == 0) {
            $payload['restrict_type'] = null;
        }

        if (count($payload['blocked_funnels'] ?? []) == 0) {
            $payload['blocked_funnels_type'] = null;
        }

        try {

            $result = $this->repository->update($id, $payload);

            return response()->json([
                'success' => $result
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'error' => [$ex->getMessage()]
            ], 422);
        }
    }
}
