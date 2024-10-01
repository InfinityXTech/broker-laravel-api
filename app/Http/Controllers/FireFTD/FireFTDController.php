<?php

namespace App\Http\Controllers\FireFTD;

use Illuminate\Http\Request;
use Illuminate\FireFTD\Facades\Validator;
// use Illuminate\FireFTD\Facades\Route;

use App\Repository\FireFTD\IFireFTDRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/fireftd"
 * )
 * @OA\Tag(
 *     name="fireftd",
 *     description="User related operations"
 * )
 */
class FireFTDController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IFireFTDRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/fireftd",
     *  security={{"bearerAuth":{}}},
     *  tags={"fireftd"},
     *  summary="Get fireftd",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run(Request $request)
    {
        /*$validator = Validator::make($request->all(), [
            // 'partner_type' => 'required|integer',
            // 'company_type' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->run($payload);

        return response()->json($model, 200);*/
		return true;
    }	
	
}
