<?php

namespace App\Http\Controllers\MarketingReport;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
// use Illuminate\Support\Facades\Route;

use App\Repository\MarketingReport\IMarketingReportRepository;
use App\Http\Controllers\ApiController;

use OpenApi\Annotations as OA;

/**
 * @OA\PathItem(
 * path="/api/marketing_report"
 * )
 * @OA\Tag(
 *     name="marketing_report",
 *     description="User related operations"
 * )
 */
class MarketingReportController extends ApiController
{
    private $repository;

    /**
     * Create a new FormController instance.
     *
     * @return void
     */
    public function __construct(IMarketingReportRepository $repository)
    {
        $this->repository = $repository;
        $this->middleware('auth:api', ['except' => []]);

        // active
        $this->middleware('permissions:marketing_reports[active=1]', []);
        // view
        $this->middleware('permissions:marketing_reports[access=all|access=view|access=add|access=edit]', ['only' => ['run', 'download', 'pivot', 'metrics']]);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_report",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_report"},
     *  summary="Get marketing_report",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function run(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'adjustment' => 'boolean|nullable',
            'timeframe' => 'required|string|min:2',
            'advertiser' => 'array|nullable',
            'affiliate' => 'array|nullable',
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

        $model = $this->repository->run($payload);

        $count = count($model['items'] ?? []);
        if ($count > 3000) {
            return response()->json([
                'data' => 'Found ' . $count . ' rows, more then allow - 3k. Please add additional conditions to the filter'
            ], 422);
        }

        return response()->json($model, 200);
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_report/download",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_report"},
     *  summary="Get marketing_report",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'adjustment' => 'boolean|nullable',
            'timeframe' => 'required|string|min:2',
            'advertiser' => 'array|nullable',
            'affiliate' => 'array|nullable',
            'country' => 'array|nullable',
            'language' => 'array|nullable',
            // 'account_manager' => 'array|nullable',
            'metrics' => 'array',
            'pivot' => 'array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        // $fileName = 'marketing_report.csv';
        $fileName = 'export_bi_' . date('Y-m-d') . '.csv';
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

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/marketing_report/pivot",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_report"},
     *  summary="Get marketing_report",
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
     *  path="/api/marketing_report/metrics",
     *  security={{"bearerAuth":{}}},
     *  tags={"marketing_report"},
     *  summary="Get marketing_report",
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
}
