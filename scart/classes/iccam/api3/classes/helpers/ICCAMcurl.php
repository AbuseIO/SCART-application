<?php
namespace abuseio\scart\classes\iccam\api3\classes\helpers;

// https://api-demo.iccam.net/swagger/index.html
// https://iccamapi.notion.site/iccamapi/ICCAM-API-3-0-Beta-Documentation-1d42601f7faf458095812d95b0e4ff5e

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\Token;
use Winter\Storm\Network\Http;

class ICCAMcurl {

    private static $_debug = false;

    private static $_channel = '';
    private static $_urlroot = '';
    private static $_cookie = '';
    private static $_token = '';
    private static $_curltimeout = 10;

    private static $_curlerror = false;             // ICCAM error
    private static $_curlerrortext = '';            // ICCAM error text
    private static $_curlerrorretry = 3;            // number of times before alert admin
    private static $_curlerroroffline = false;      // error status offline

    public static function setDebug($debug) {
        self::$_debug = $debug;
    }

    public static function connect()
    {
        if (self::$_channel == '') {
            if (self::$_debug) scartLog::logLine("D-ICCAMurl (connect)");
            self::$_cookie = Systemconfig::get('abuseio.scart::iccam.cookie', '');
            if (!self::$_cookie) self::$_cookie = temp_path() . '/iccam.txt';
            self::$_urlroot = Systemconfig::get('abuseio.scart::iccam.urlroot', '');
            self::$_channel = curl_init();
        }
    }

    public static function init($url) {

        if (self::$_debug) scartLog::logLine("D-ICCAMurl (init)");

        // needed for ICCAM, else 'hanging'
        //curl_reset(self::$_channel);

        // set needed defaults for ICCAM
        curl_setopt(self::$_channel, CURLOPT_URL, $url);
        curl_setopt(self::$_channel, CURLOPT_PORT, '443');
        curl_setopt(self::$_channel, CURLOPT_CAINFO, Systemconfig::get('abuseio.scart::iccam.cacert', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLCERT, Systemconfig::get('abuseio.scart::iccam.sslcert', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLCERTPASSWD, Systemconfig::get('abuseio.scart::iccam.sslcertpw', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLKEY, Systemconfig::get('abuseio.scart::iccam.sslkey', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLKEYPASSWD, Systemconfig::get('abuseio.scart::iccam.sslkeypw', ''));
        curl_setopt(self::$_channel, CURLOPT_SSL_VERIFYPEER, Systemconfig::get('abuseio.scart::iccam.verifypeer', true));
        curl_setopt(self::$_channel, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$_channel, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(self::$_channel, CURLOPT_COOKIESESSION, true);
        curl_setopt(self::$_channel, CURLOPT_COOKIEJAR, self::$_cookie);
        curl_setopt(self::$_channel, CURLOPT_COOKIEFILE, self::$_cookie);
        curl_setopt(self::$_channel, CURLOPT_CONNECTTIMEOUT, self::$_curltimeout);
        curl_setopt(self::$_channel, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt(self::$_channel, CURLOPT_PRIVATE, true);
        curl_setopt(self::$_channel, CURLINFO_HEADER_OUT, true);

    }

    public static function close()
    {
        if (self::$_channel != '') {
            if (self::$_debug) scartLog::logLine("D-ICCAMurl (close)");
            curl_close(self::$_channel);
            self::$_channel = '';
            self::$_loggedin = false;
        }
    }


    public static function setCredentials($token)
    {
        if (self::$_debug) scartLog::logLine("D-ICCAMurl (setCredentials)");
        self::$_token = $token;
    }

    static function setHeader($contentLength=0) {

        $header = array(
            'Authorization: Bearer '.self::$_token,
            'Content-Type: application/json',
            'accept: application/json',
            'Content-Length: ' . $contentLength
        );
        //if (self::$_debug) scartLog::logDump("D-ICCAMurl (setHeader); header=",$header);
        curl_setopt(self::$_channel,CURLOPT_HTTPHEADER,$header);
    }

    /**
     * call ICCAM interface by curl
     *
     * to kind of errors; general, network, not found, etc; we see this as offline
     * error report(s) from ICCAM; http-code=400; get error text
     * Calling function can distinct from this
     *
     * @return bool|mixed|string
     */

    static function call_curl() {

        // start always positive
        self::$_curlerror = self::$_curlerroroffline =false;
        self::$_curlerrortext = '';

        $result = curl_exec(self::$_channel);
        if (self::$_debug) scartLog::logDump("D-ICCAMurl (call_curl); result=",$result);

        // check always also http return code
        $info = curl_getinfo(self::$_channel);
        if (isset($info['http_code'])) {
            if (self::$_debug) scartLog::logDump("D-ICCAMurl (call_curl); http_code=",$info['http_code']);
            // if ERROR then log error and skip transaction - if tmp error then log error and retry transaction (offline status)
            if (in_array($info['http_code'],['500','400','401','403'])) {

                // server error reporting

                // 500; server error
                // 400; failure request
                // 401; unauthorized (sub) call
                // 403; forbidden call

                self::$_curlerror = true;
                $result = @json_decode($result);
                if (isset($result->errors)) {
                    $errors = $result->errors;
                } else {
                    $errors = '(unknown); http_code=' . $info['http_code'];
                    scartLog::logDump("W-ICCAMurl; $errors; result=", $result);
                }
                $error = print_r($errors,true);
                self::$_curlerrortext = $error;

                // set  error
                $result = false;

            } elseif ($info['http_code'] != '200') {

                // offline - retry
                self::$_curlerroroffline = true;

                $error = "not valid http code: ".$info['http_code'].", text: ".self::httpCodeToText($info['http_code']);
                scartLog::logLine("W-ICCAMurl; $error");
                self::$_curlerrortext = $error;

                // get error object returned by ICCAM
                $result = @json_decode($result);
                scartLog::logDump("W-ICCAMurl; error: ",$result);
                // set  error
                $result = false;
            }

        } elseif (curl_errno(self::$_channel) !== 0) {

            // offline - retry
            self::$_curlerroroffline = true;
            $error = "CURL ERROR (no): ".curl_errno(self::$_channel);
            scartLog::logLine("W-ICCAMurl; $error");
            self::$_curlerrortext = $error;

            $result = false;
        }

        if (self::$_curlerroroffline) {

            // ICCAM interface sometimes not available -> inform admin with one time message (after retry count)

            if (empty($error)) $error = curl_getinfo(self::$_channel);
            scartLog::logDump("W-ICCAMurl; Error: ",$error);

            $sendalert = scartUsers::getGeneralOption('ICCAM_CURL_ERROR');
            if (empty($sendalert)) $sendalert = 1;
            $sendalert = intval($sendalert) + 1;
            // check retry country
            if ($sendalert == self::$_curlerrorretry) {
                // (ONE TIME) send admin CURL error
                $params = [
                    'reportname' => 'ICCAM INTERFACE ERROR; retry count=' . $sendalert,
                    'report_lines' => [
                        "CURL_ERROR=" . print_r($error, true)
                    ]
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
            }
            scartUsers::setGeneralOption('ICCAM_CURL_ERROR', $sendalert);

            scartLog::logLine("W-ICCAMur; error retry count: $sendalert");

        } elseif (!self::$_curlerror)  {

            //scartLog::logLine("D-ICCAMurl (send); result=".print_r($result,true));

            if ($result) {
                $result = json_decode($result);
            }

            $sendalert = scartUsers::getGeneralOption('ICCAM_CURL_ERROR');
            if ($sendalert!='') {
                // Reset if error was set
                if (intval($sendalert) >= self::$_curlerrorretry) {
                    // (ONE TIME) send admin reset error
                    $params = [
                        'reportname' => 'ICCAM CURL IS WORKING (AGAIN); retry count='. $sendalert,
                        'report_lines' => [
                            "NO CURL_ERROR"
                        ]
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
                }
                scartUsers::setGeneralOption('ICCAM_CURL_ERROR', '');
            }

        }
        return $result;
    }

    public static function isOffline() {
        return (self::$_curlerroroffline);
    }

    public static function hasErrors() {
        return (self::$_curlerror);
    }

    public static function getErrors() {
        return (self::$_curlerrortext);
    }

    /**
     * @param string $request
     * @param $url
     * @param bool $returnJson
     * @param bool $return
     * @return bool|mixed|string
     * @throws IccamException
     */
    public static function send ($request = 'GET', $action='', $postdata='') {

        $url = self::$_urlroot . $action;
        self::init($url);
        if ($request == 'GET') {
            // default GET
            curl_setopt(self::$_channel, CURLOPT_CUSTOMREQUEST, $request);
            curl_setopt(self::$_channel, CURLOPT_HTTPGET, true);
            $contentLength = 0;
        } else {
            // Note: for ICCAM numbers must be numbers (JSON_NUMERIC_CHECK)
            $postjson = ($postdata) ? json_encode($postdata,JSON_NUMERIC_CHECK ) : '';
            if (self::$_debug) scartLog::logLine("D-ICCAMurl: POST json=$postjson");
            curl_setopt(self::$_channel, CURLOPT_CUSTOMREQUEST, $request);
            curl_setopt(self::$_channel, CURLOPT_POSTFIELDS, $postjson);
            curl_setopt(self::$_channel, CURLINFO_HEADER_OUT, true);
            curl_setopt(self::$_channel, CURLOPT_HEADEROPT, CURLHEADER_UNIFIED);
            $contentLength = strlen($postjson);
        }
        // set header with Bearer and length of Json data
        self::setHeader($contentLength);
        if (self::$_debug) scartLog::logLine("D-ICCAMurl: $request url=$url");
        $result = self::call_curl();
        return $result;
    }


    /**
     * Translate http code to http readable text
     *
     * @param $code
     * @param string $text
     * @return mixed|string
     */


    public static function httpCodeToText($code, $text = '')
    {
        if ($code !== NULL) {

            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default: $text = 'Unknown http code'; break;
            }
        }

        return $text;
    }

}
