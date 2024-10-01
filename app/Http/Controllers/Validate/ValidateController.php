<?php

namespace App\Http\Controllers\Validate;

use App\Helpers\GeneralHelper;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Validator;

use App\Repository\EmailsChecker\IEmailsCheckerRepository;
use Merkeleon\PhpCryptocurrencyAddressValidation\Validation;

/**
 * @OA\PathItem(
 * path="/api/validate"
 * )
 * @OA\Tag(
 *     name="validate",
 *     description="User related operations"
 * )
 */
class ValidateController extends ApiController
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
    }

    /**
     * Run Form
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/validate/cryptocurrency-address/{iso_code}/{wallet_address}",
     *  security={{"bearerAuth":{}}},
     *  tags={"validate"},
     *  summary="Get validate",
     *       @OA\Parameter(
     *          name="iso_code",
     *          in="path",
     *          required=true, 
     *      ),
     *       @OA\Parameter(
     *          name="wallet_address",
     *          in="path",
     *          required=true, 
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),
     * )
     */
    public function cryptocurrency_address(string $iso_code, string $wallet_address)
    {
        if (!in_array($iso_code, ['eth', 'trx', 'btc'])) {
            $iso_code = 'eth';
        }
        $validator = Validation::make(strtoupper($iso_code));
        $result = $validator->validate($wallet_address);
        return response()->json(['validate' => $result], 200);
    }
}
