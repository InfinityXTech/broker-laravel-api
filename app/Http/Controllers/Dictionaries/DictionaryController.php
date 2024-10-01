<?php

namespace App\Http\Controllers\Dictionaries;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Dictionaries\IDictionaryRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/dictionary"
 * )
 * @OA\Tag(
 *     name="dictionary",
 *     description="User related operations"
 * )
 */
class DictionaryController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IDictionaryRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get dictionaries",
     *       @OA\Parameter(
     *          name="dictionaries",
     *          in="query",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function index(Request $request)
    {
        $dictionaries = $request->get('dictionaries');
        $dictionaries_array = explode(',', $dictionaries);
        return response()->json($this->repository->index($dictionaries_array), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary/brokers",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get brokers",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function brokers()
    {
        return response()->json($this->repository->brokers(), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary/traffic_endpoints",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get traffic_endpoints",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function traffic_endpoints()
    {
        return response()->json($this->repository->traffic_endpoints(), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary/countries",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get countries",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function countries()
    {
        return response()->json($this->repository->countries(), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary/languages",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get languages",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function languages()
    {
        return response()->json($this->repository->languages(), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary/currency_rates",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get currency rates",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function currency_rates(Request $request)
    {
        $datetime = $request->get('datetime', '');
        return response()->json($this->repository->currency_rates($datetime ?? ''), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary/regions",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get currency rates",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function regions(Request $request)
    {
        $country_code = $request->get('country_code', '');
        return response()->json($this->repository->regions($country_code ?? ''), 200);
    }

    /**
     * @OA\Get(
     *  path="/api/dictionary/marketing_post_events",
     *  tags={"dictionary"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get currency rates",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function marketing_post_events(Request $request)
    {
        $advertiserId = $request->get('advertiserId', '');
        return response()->json($this->repository->marketing_post_events($advertiserId ?? ''), 200);
    }
}
