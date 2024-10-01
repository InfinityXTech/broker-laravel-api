<?php
namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Annotations as OA;

/** 
 * @OA\Info(
 *     version="1.0",
 *     title="API",
 *     description="info",
 *     @OA\Contact(name="Swagger API Team")
 * )
 * @OA\Server(
 *     url="https://qa3-api.albertroi.com",
 *     description="API server"
 * )
 * @OA\Schemes(format="https")
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      in="header",
 *      name="Authorization",
 *      type="http",
 *      scheme="Bearer",
 *      bearerFormat="JWT",
 * ),
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
}
