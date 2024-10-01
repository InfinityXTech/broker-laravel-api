<?php

namespace App\Http\Controllers\Auth;

use Exception;
use App\Models\User;
use App\Classes\Access;

use Illuminate\Http\Request;
use App\Helpers\ClientHelper;

use Illuminate\Http\Response;
use App\Helpers\GeneralHelper;
use Illuminate\Support\Carbon;
use App\Events\NotificationEvent;
use App\Models\MarketingAffiliate;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\NotificationMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Classes\Mongo\MongoDBObjects;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cookie;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\Notifications\BroadcastNotification;
use App\Repository\Affiliates\AffiliateRepository;

/**
 * @OA\PathItem(
 * path="/api/auth"
 * )
 * @OA\Tag(
 *     name="auth",
 *     description="User related operations"
 * )
 */
class AuthController extends ApiController
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'google', 'refresh', 'register']]);
    }

    private function get_user_session_hash($user)
    {
        return md5(ClientHelper::clientId() . $user['account_email'] . $user['qr_secret']);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    /**
     * @OA\Post(
     *  path="/api/auth/login",
     *  tags={"auth"},
     *  summary="Login",
     *       @OA\Parameter(
     *          name="email",
     *          in="query",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="password",
     *          in="query",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();

        $credentials = [
            'account_email' => $data['email'],
            'password' => $data['password'],
        ];

        $user = User::query()->where('account_email', '=', $data['email'])->first(['_id', 'account_email', 'password', 'qr_secret', 'status', 'roles']);
        // GeneralHelper::PrintR([ClientHelper::clientId()]);die();
        // GeneralHelper::PrintR($user->toArray());die();
        // GeneralHelper::PrintR([$user->toSql()]);die();

        if ($user) {
            $user = $user->toArray();
        } else {
            $user = [];
        }

        if (((int)($user['status'] ?? 0)) == 0) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized'
            ], 401);
        }

        $token = Auth::attempt($credentials);
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized'
            ], 401);
        }

        $midnight = strtotime('tomorrow midnight');
        $seconds = max(60 * 30, ($midnight - time()));

        // $timezone = $request->get('timezone');
        // $expiry = Carbon::now()->addMinutes($minutes);
        // if ($timezone) {
        //     $expiry = $expiry->setTimezone($timezone);
        // }

        // $data['expire'] = strtotime('+' . $minutes . ' MINUTES'); //$expiry->timestamp;
        // $secret = json_encode($data);
        // $auth_token = encrypt($secret);

        $session_hash = $this->get_user_session_hash($user);
        Cache::put(
            $session_hash,
            [
                'id' => (string)$user['_id'],
                'email' => $data['email'],
                'password' => $data['password'],
                'qr_secret' => $user['qr_secret'],
                'token' => $token
            ],
            $seconds
        );

        return response()
            ->json([
                'success' => true,
                'session_hash' => $session_hash,
                'ttl' => $seconds
            ]);
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/auth/google",
     *  tags={"auth"},
     *  summary="Register",
     *       @OA\Parameter(
     *          name="code",
     *          in="query",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function google(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'session_hash' => 'string|min:2|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $data = $validator->validated();

        $session_hash = $data['session_hash'];
        $auth_token = Cache::get($session_hash);
        if (!$auth_token) {
            return response()->json([
                'success' => false,
                'error' => 'User is not authorized'
            ], 200);
        }

        if (empty($auth_token['qr_secret'])) {
            return response()->json([
                'success' => false,
                'error' => 'Secret in the session is empty'
            ], 200);
        }

        $ga = new \PHPGangsta_GoogleAuthenticator();
        $oneCode = $ga->getCode($auth_token['qr_secret']);

        if ($oneCode == $data['code'] || !app()->isProduction()) {
            if (empty($auth_token)) {
                return response()->json([
                    'success' => true,
                    'code_valid' => true,
                    'access_token' => false
                ], 200);
            }

            $user = User::findOrFail($auth_token['id']);
            $user->update(['last_auth_time' => new \MongoDB\BSON\UTCDateTime()]);

            return $this->createNewToken($session_hash, $auth_token['token']);
        }

        return response()->json([
            'success' => true,
            'code_valid' => false,
            'error' => 'Google code is not valid',
        ], 200);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/auth/refresh",
     *  tags={"auth"},
     *  summary="Refresh",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_hash' => 'string|min:2|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $data = $validator->validated();

        if ($data['session_hash']) {
            $auth_data = Cache::get($data['session_hash']);
            if ($auth_data) {
                // $newToken = JWTAuth::parseToken()->refresh();
                // JWTAuth::setToken($auth_data['token']);
                // $newToken = JWTAuth::refresh($auth_data['token']);

                $credentials = [
                    'account_email' => $auth_data['email'],
                    'password' => $auth_data['password'],
                ];

                $newToken = Auth::attempt($credentials);

                if ($newToken) {

                    $midnight = strtotime('tomorrow midnight');
                    $seconds = ($midnight - time()); // today and tomorrow

                    $user = User::query()->where('account_email', '=', $auth_data['email'])->get(['_id', 'account_email', 'qr_secret'])->first();

                    $user->update(['last_auth_time' => new \MongoDB\BSON\UTCDateTime()]);

                    $session_hash = $this->get_user_session_hash($user);
                    Cache::put(
                        $session_hash,
                        [
                            'id' => $user->_id,
                            'email' => $auth_data['email'],
                            'password' => $auth_data['password'],
                            'qr_secret' => $user->qr_secret,
                            'token' => $newToken
                        ],
                        $seconds
                    );

                    return $this->createNewToken($session_hash, $newToken);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthorized'
                    ], 401);
                }
            }
        }
        return response()->json([
            'success' => false,
            'error' => 'Session Expired'
        ], 401);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/auth/renewal",
     *  tags={"auth"},
     *  summary="Refresh",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function renewal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_hash' => 'string|min:2|nullable',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $data = $validator->validated();

        if ($data['session_hash']) {
            $auth_data = Cache::get($data['session_hash']);
            if ($auth_data) {

                // $newToken = JWTAuth::parseToken()->refresh();

                $token = JWTAuth::getToken();

                if (!$token) {
                    throw new \Exception('Token not provided');
                }
                try {
                    $newToken = JWTAuth::refresh($token);
                } catch (\Exception $e) {
                    // throw new \Exception('The token is invalid');
                    $auth_data = Cache::get($data['session_hash']);
                    if ($auth_data) {
                        $credentials = [
                            'account_email' => $auth_data['email'],
                            'password' => $auth_data['password'],
                        ];
                        $newToken = Auth::attempt($credentials);
                    }
                }

                if ($newToken) {

                    JWTAuth::setToken($newToken);

                    $midnight = strtotime('tomorrow midnight');
                    $seconds = ($midnight - time()); // today and tomorrow

                    $user = User::query()->where('account_email', '=', $auth_data['email'])->get(['_id', 'account_email', 'qr_secret'])->first();

                    $user->update(['last_auth_time' => new \MongoDB\BSON\UTCDateTime()]);

                    $session_hash = $this->get_user_session_hash($user);
                    Cache::put(
                        $session_hash,
                        [
                            'id' => $user->_id,
                            'email' => $auth_data['email'],
                            'password' => $auth_data['password'],
                            'qr_secret' => $user->qr_secret,
                            'token' => $newToken
                        ],
                        $seconds
                    );

                    return $this->createNewToken($session_hash, $newToken);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthorized'
                    ], 401);
                }
            }
        }
        return response()->json([
            'success' => false,
            'error' => 'Session Expired'
        ], 401);
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/auth/register",
     *  tags={"auth"},
     *  summary="Register",
     *       @OA\Parameter(
     *          name="username",
     *          in="query",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="email",
     *          in="query",
     *          required=true,
     *      ),
     *       @OA\Parameter(
     *          name="password",
     *          in="query",
     *          required=true,
     *      ),
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            // 'password' => 'required|string|confirmed|min:4',
            'password' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $ga = new \PHPGangsta_GoogleAuthenticator();
        $secret = $ga->createSecret();
        // $qrCodeUrl = $ga->getQRCodeGoogleUrl('Jimmywho', $secret);

        $user = User::create(array_merge(
            $validator->validated(),
            [
                'account_email' => $request->email,
                'qr_secret' => $secret,
                'password' => bcrypt($request->password)
            ]
        ));
        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user
        ], 201);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Post(
     *  path="/api/auth/logout",
     *  security={{"bearerAuth":{}}},
     *  tags={"auth"},
     *  summary="Logout",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/auth/profile",
     *  tags={"auth"},
     *  security={{"bearerAuth":{}}},
     *  summary="User Profile",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function userProfile(Request $request)
    {
        $user = Auth::user();
        // $token = JWTAuth::getToken(); // parseToken()->authenticate();
        $token = $request->bearerToken();

        Access::attach_custom_access($user);

        $user_data = [
            'id' => $user->_id,
            'account_email' => $user->account_email,
            'name' => $user->name,
            'username' => $user->username,
            'status' => $user->status,
            'permissions' => $user->permissions,
            'client' => $user->client,
            'roles' => $user->roles,
            'token' => $token,
            'systemId' => $user->systemId ?? 'crm',
            'session_hash' => $this->get_user_session_hash($user)
        ];

        return response()->json($user_data);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken(string $session_hash, string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'session_hash' => $session_hash,
        ]);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Get(
     *  path="/api/auth/user_profile_data/{name}",
     *  tags={"auth"},
     *  security={{"bearerAuth":{}}},
     *  summary="User Profile",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function getUserProfileData(string $name)
    {
        $user = Auth::user();
        $user_data = $user->get_profile($name);
        return response()->json($user_data);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * @OA\Put(
     *  path="/api/auth/user_profile_data/{name}",
     *  tags={"auth"},
     *  security={{"bearerAuth":{}}},
     *  summary="User Profile",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function setUserProfileData(string $name, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $payload['data'] ??= [];

        $user = Auth::user();
        $user_data = $user->set_profile($name, $payload['data']);

        return response()->json($user_data);
    }

    /**
     * @OA\Post(
     *  path="/api/auth/affiliate/register",
     *  tags={"auth"},
     *  summary="Affiliate Register",
     *   @OA\Response(response=200, description="successful operation"),
     *   @OA\Response(response=406, description="not acceptable"),
     *   @OA\Response(response=500, description="internal server error"),

     * )
     */
    public function affiliate_register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string',
            'email' => 'required|email',
            'skype' => 'nullable|string',
            'telegram' => 'nullable|string',
            'whatsapp' => 'nullable|string',
            'password' => 'required|string|min:4',
            'confirm_password' => 'required|string|min:4',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $payload = $validator->validated();

        $affiliate = new AffiliateRepository();
        $result = ['success' => true];
        try {
            $result['success'] = $affiliate->register($payload);
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['error'] = $ex->getMessage();
        }
        return response()->json($result);
    }
}
