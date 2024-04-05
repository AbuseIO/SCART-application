<?php
namespace abuseio\scart\classes\iccam\api3\classes\helpers;

// https://api-demo.iccam.net/swagger/index.html
// https://iccamapi.notion.site/iccamapi/ICCAM-API-3-0-Beta-Documentation-1d42601f7faf458095812d95b0e4ff5e

use abuseio\scart\classes\iccam\api3\models\Tokens;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\Token;
use Winter\Storm\Network\Http;

class ICCAMAuthentication {

    private static $_debug = false;
    private static $_loggedin = false;
    private static $_token = '';
    private static $_refreshtoken = '';
    private static $_expiresIn = '';
    private static $_delayafterlogin = 2;

    public static function login($calling='') {

        $calling = ($calling) ? "$calling; " : '';
        if (!self::$_loggedin) {
            ICCAMcurl::connect();
            $result = ICCAMcurl::send('POST','Authentication', [
                'username' => Systemconfig::get('abuseio.scart::iccam.apiuser', ''),
                'password' => Systemconfig::get('abuseio.scart::iccam.apipass', ''),
            ]);
            if ($result) {

                scartAlerts::alertAdminStatus('ICCAM_AUTHENTICATION',$calling, false);

                if (self::$_debug) scartLog::logLine("D-ICCAMurl(login): data".print_r($result,true));
                if (isset($result->bearerToken)) {
                    self::$_token = $result->bearerToken;
                    self::$_refreshtoken = (isset($result->refreshToken) ? $result->refreshToken : '');
                    $refreshsecs = (isset($result->bearerTokenExpiresIn) ? $result->bearerTokenExpiresIn : 1800);
                    self::$_expiresIn = time() + intval($refreshsecs);
                    scartLog::logLine("D-".$calling."ICCAMurl(login): token valid until (utc): ".date('Y-m-d H:i:s',self::$_expiresIn));
                    if (self::$_debug) scartLog::logLine("D-".$calling."ICCAMurl(login): token=".self::$_token);

                    // 2023/9/8 delay is needed for ICCAM to be ready for the next (tokenized) ICCAM call
                    scartLog::logLine("D-".$calling."ICCAMurl(login): delay ".self::$_delayafterlogin." second(s) to give ICCAM time to init this login/token");
                    sleep(self::$_delayafterlogin);

                } else {
                    $error = 'no bearerToken received';
                    scartLog::logLine("E-".$calling."ICCAMurl(login): $error");
                    scartAlerts::alertAdminStatus('ICCAM_AUTHENTICATION',$calling, true, $error, 3 );
                }

            } else {

                $error = ICCAMcurl::getErrors();
                scartAlerts::alertAdminStatus('ICCAM_AUTHENTICATION',$calling, true, $error, 3 );

            }
            self::$_loggedin = ($result !== false);

        } else {
            // To-Do: if errors then login=false
            //if (ICCAMcurl::hasErrors()) self::$_loggedin = false;
            scartLog::logLine("D-".$calling."ICCAMurl(login): reuse login token; valid until (utc): ".date('Y-m-d H:i:s',self::$_expiresIn));
        }
        return self::$_loggedin;
    }

    /**
     * @Description logout
     * @return bool|mixed|string
     */
    public static function logout()
    {
        ICCAMcurl::connect();
        $result = ICCAMcurl::send('POST','Authentication/logout');
        ICCAMcurl::close();
        self::$_token = self::$_refreshtoken = '';
        self::$_loggedin = false;
    }


    /**
     * @return bool|mixed|string
     */
    public function getUserInfo()
    {
        return $this->send('GET', 'Authentication/userinfo', true);
    }

    /**
     * @return bool|mixed|string
     */
    public function refreshToken()
    {
        if (self::$_debug) scartLog::logLine("D-ICCAMurl(refreshToken)");
        ICCAMcurl::connect();
        $result = ICCAMcurl::send('POST','Authentication/refresh', [
            'bearerToken' => self::$_token,
            'refreshToken' => self::$_refreshtoken,
        ]);
        if ($result) {
            if (self::$_debug) scartLog::logLine("D-ICCAMurl(refreshToken): data".print_r($result,true));
            if (isset($result->bearerToken)) {
                self::$_token = $result->bearerToken;
                self::$_refreshtoken = (isset($result->refreshToken) ? $result->refreshToken : '');
                $refreshsecs = (isset($result->bearerTokenExpiresIn) ? $result->bearerTokenExpiresIn : 1800);
                self::$_expiresIn = time() + intval($refreshsecs);
                scartLog::logLine("D-ICCAMurl(refreshToken): token valid until (utc): ".date('Y-m-d H:i:s',self::$_expiresIn));
            }
        }
        return $result;
    }

    public static function isLoggedin() {

        return self::$_loggedin;
    }

    //\\//\\       End Authentication       //\\//\\


    /**
     * @return mixed
     */
    public static function getToken() {

        if (time() > self::$_expiresIn) {
            scartLog::logLine("W-ICCAMurl(getToken): token expired - refresh token");
            self::refreshToken();
        }
        return self::$_token;
    }


    /**
     * @Description check if we can use the token for request data
     * @param bool $bool
     * @return bool
     */
    public static function isTokenValid($token, $status = false){

        $scartCurl = new ICCAMcurl();
        $scartCurl->setPostfield(CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$token, 'Content-Type: application/json', 'accept: application/json']);
        $userinfo = $scartCurl->getUserInfo();

        if($userinfo && $userinfo->isAuthenticated && $userinfo->exposedClaims->exp >= time()) {
            $status = (($userinfo->exposedClaims->exp - (60 * 3)) >= time()) ? true : 'refresh';
        }

        return $status;
    }

    public function getexposedClaims($token, $status = false){
        $scartCurl = new ICCAMcurl();
        $scartCurl->setPostfield(CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$token, 'Content-Type: application/json', 'accept: application/json']);
        $userinfo = $scartCurl->getUserInfo();

        return (!empty($userinfo)) ? $userinfo->exposedClaims : false;
    }

}
