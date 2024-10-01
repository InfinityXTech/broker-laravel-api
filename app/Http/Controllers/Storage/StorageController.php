<?php

namespace App\Http\Controllers\Storage;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\Storage\IStorageRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/storage"
 * )
 * @OA\Tag(
 *     name="storage",
 *     description="User related operations"
 * )
 */
class StorageController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IStorageRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);
    }

    /**
     * @OA\Get(
     *  path="/api/storage/{fileId}",
     *  tags={"storage"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get storage file",
     *       @OA\Parameter(
     *          name="fileId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function get(string $fileId)
    {
        return response()->json($this->repository->findById($fileId), 200);
    }

    /**
     * Create Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/storage/download/{fileId}",
     *  security={{"bearerAuth":{}}},
     *  tags={"storage"},
     *  summary="Download storage",
     *       @OA\Parameter(
     *          name="fileId",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function download(string $fileId)
    {
        $file = $this->repository->info($fileId);
        if (!$file) {
            return abort(404);
        }
        $content = $this->repository->content($fileId);
        return response()->streamDownload(function() use($content) { echo $content; }, $file['original_file_name']);
    }
    
}
