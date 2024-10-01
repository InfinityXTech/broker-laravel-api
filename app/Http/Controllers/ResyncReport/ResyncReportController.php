<?php

namespace App\Http\Controllers\ResyncReport;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;

use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Gate;

use App\Http\Controllers\ApiController;
use App\Models\ReSync;
use Illuminate\Support\Facades\Validator;
use App\Repository\Leads\ILeadsRepository;

class ResyncReportController extends ApiController
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
     *  path="/api/resync_report",
     *  tags={"resync_report"},
     *  security={{"bearerAuth":{}}},
     *  summary="Get all resync_report",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function resync_report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'required|string|min:2',
            'endpoint' => 'string|nullable',
            'status' => 'string|nullable',
            'leadId' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $string = $payload['timeframe'];
        $explode = explode(' - ', $string);

        $start = strtotime($explode[0] . " 00:00:00");
        $end = strtotime($explode[1] . " 23:59:59");

        $start = new \MongoDB\BSON\UTCDateTime($start * 1000);
        $end = new \MongoDB\BSON\UTCDateTime($end * 1000);

        $query = ReSync::query()->where('created_at', '>=', $start)->where('created_at', '<=', $end);

        if (!empty($payload['endpoint'])) {
            $query = $query->where('endpoint', '=', $payload['endpoint']);
        }

        if (!empty($payload['status'])) {
            $query = $query->whereIn('status',[(string)$payload['status'], (int)$payload['status']]);
        }

        if (!empty($payload['leadId'])) {
            $query = $query->where(function ($q) use ($payload) {
                $q
                    ->orWhere('leads.id', '=', $payload['leadId'])
                    ->orWhere('duplicate_leads.id', '=', $payload['leadId']);
            });
        }

        // GeneralHelper::PrintR([$query->toSql()]);die();
        $result = $query
            ->with([
                'created_by_user:name,account_email',
                'endpoint_data:token'
            ])
            ->get();

        return response()->json($result, 200);
    }
}
