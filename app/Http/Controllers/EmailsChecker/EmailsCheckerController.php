<?php

namespace App\Http\Controllers\EmailsChecker;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Repository\EmailsChecker\IEmailsCheckerRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/emailschecker"
 * )
 * @OA\Tag(
 *     name="emailschecker",
 *     description="User related operations"
 * )
 */
class EmailsCheckerController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IEmailsCheckerRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        $this->middleware('permissions:brokers[tools]', ['only' => ['run']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/emailschecker",
     *  security={{"bearerAuth":{}}},
     *  tags={"emailschecker"},
     *  summary="Get emailschecker",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'data' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $file = $this->repository->run($payload);
        $fileName = 'export_' . date('Y-m-d') . '.xlsx';

        /* Redirect output to a client’s web browser (Excel5)*/
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment; filename=\"$fileName\"");
        header('Cache-Control: max-age=0');
        return $file;
    }
}
