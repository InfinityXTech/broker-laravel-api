<?php

namespace App\Http\Controllers\QualityReport;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\QualityReport\IQualityReportRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/quality_report"
 * )
 * @OA\Tag(
 *     name="quality_report",
 *     description="User related operations"
 * )
 */
class QualityReportController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IQualityReportRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:quality_reports[active=1]', []);
        // view
        $this->middleware('permissions:quality_reports[access=all|access=view|access=add|access=edit]', ['only' => ['run', 'pivot', 'metrics']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/quality_report",
     *  security={{"bearerAuth":{}}},
     *  tags={"quality_report"},
     *  summary="Get quality_report",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string|min:2',
            'broker' => 'array|nullable',
            'traffic_endpoint' => 'array|nullable',
            'country' => 'array|nullable',
            'language' => 'array|nullable',
            'account_manager' => 'array|nullable',
            'metrics' => 'array|nullable',
            'pivot' => 'array|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $model = $this->repository->run($payload);

        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/quality_report/pivot",
     *  security={{"bearerAuth":{}}},
     *  tags={"quality_report"},
     *  summary="Get quality_report",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function pivot(Request $request)
    {
        $model = $this->repository->pivot();

        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/quality_report/metrics",
     *  security={{"bearerAuth":{}}},
     *  tags={"quality_report"},
     *  summary="Get quality_report",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function metrics(Request $request)
    {
        $model = $this->repository->metrics();

        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/quality_report/download",
     *  security={{"bearerAuth":{}}},
     *  tags={"report"},
     *  summary="Get report",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'adjustment' => 'boolean|nullable',
            'timeframe' => 'required|string|min:2',
            'broker' => 'array|nullable',
            'traffic_endpoint' => 'array|nullable',
            'country' => 'array|nullable',
            'language' => 'array|nullable',
            'account_manager' => 'array|nullable',
            'metrics' => 'array',
            'pivot' => 'array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // $fileName = 'report.csv';
        $fileName = 'export_quality_report_' . date('Y-m-d') . '.csv';
        $headers = array(
            // "Content-type"        => "application/csv", // "text/csv",
            "Content-Type"        => "application/octet-stream",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $data = $this->repository->download($payload);
        // $data['callback']();
        return response()->streamDownload($data['callback'], $fileName, $headers);
        // return response()->json(['success' => false, 'data' => $data], 200);
    }
}
