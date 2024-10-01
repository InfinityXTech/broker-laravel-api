<?php

namespace App\Http\Controllers\Utils;

use App\Helpers\CryptHelper;
use Illuminate\Http\Request;
use App\Helpers\GeneralHelper;
// use Illuminate\Support\Facades\Route;

use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Gate;

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;
use App\Repository\Leads\ILeadsRepository;

/**
 * @OA\PathItem(
 * path="/api/utils"
 * )
 * @OA\Tag(
 *     name="utils",
 *     description="User related operations"
 * )
 */
class UtilsController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(ILeadsRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * @OA\Post(
     *  path="/utils/decrypt",
     *  security={{"bearerAuth":{}}},
     *  tags={"leads"},
     *  summary="Get leads",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function decrypt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|min:2|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $result = CryptHelper::decrypt($payload['text']);
        return response()->json($result, 200);
    }
}
