<?php
namespace App\Classes;

use Illuminate\Support\Facades\Cache;

class GoogleAuth
{

    protected $code;
    protected $userSession;
    protected $cache;
    protected $log;

    protected $ttl = 60 * 5; // 5 minutes;

    private static $instance = null;

    public static function instance($code = '')
    {
        if (isset(self::$instance)) {
            return self::$instance;
        }
        self::$instance = new self($code);
        return self::$instance;
    }

    public function __construct($code)
    {
        if (!empty($code)) {
            $this->code = $code;
            $this->userSession = $this->fetchAuth();
        }
        // $this->cache = Cache::instance();
        // $this->log = Logger::instance();
    }

    public function fetchAuth()
    {
        // return auth::get_current_user();
    }

    private function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    protected function allowAction()
    {
        $hash = $this->generateRandomString(24);
        $key = 'google_auth_' . $hash;
        Cache::put($key, 1, $this->ttl);
    }

    protected function isAllowAction($auth_key)
    {
        if (!empty($auth_key)) {
            return Cache::has($auth_key);
        }
        return false;
    }

    public static function isAllowStaticAction($auth_key)
    {
        $self = self::instance();
        return $self->isAllowAction($auth_key);
    }

    public function isValidCode()
    {
        try {
            $secret = $this->userSession['qr_secret'];

            if (empty($secret)) {
                $this->log->logError(__METHOD__ . ': Secret in the session is empty');
            }

            $ga = new \PHPGangsta_GoogleAuthenticator();
            $oneCode = $ga->getCode($secret);
            $this->log->logDebug(__METHOD__ . ': ' . $oneCode . ' == ' . $this->code);
            return ($oneCode == $this->code);
        } catch (\Exception $ex) {
            $this->log->logError(__METHOD__ . ': ' . $ex->getMessage());
        }
    }
}
