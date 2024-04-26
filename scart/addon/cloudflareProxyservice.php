<?php
namespace abuseio\scart\addon;

use abuseio\scart\classes\browse\scartCURLcalls;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\mail\scartAlerts;

/**
 * Cloudflare API
 *
 * See API documentation from cloudflare
 *
 * Test case:
 * - php artisan abuseio:testAddon -t proxy_service -c cloudflareProxyservice -u https://www.teenlovers.al
 *
 * ENVIRONMENT (.env) vars:
 * - ADDON_PROXYSERVICE_CLOUDFLARE_ID
 * - ADDON_PROXYSERVICE_CLOUDFLARE_SECRET
 *
 * Class cloudflareProxyservice
 * @package abuseio\scart\addon
 */

class cloudflareProxyservice {

    private static $_endpoint = 'https://trust.cloudflare.com/investigation/v1/{caseID}/origin';

    private static $_clientID = '';
    private static $_clientSecret = '';

    public static function init() {

        if (!SELF::$_clientID || !SELF::$_clientSecret) {
            SELF::$_clientID = env('ADDON_PROXYSERVICE_CLOUDFLARE_ID', '');
            SELF::$_clientSecret = env('ADDON_PROXYSERVICE_CLOUDFLARE_SECRET', '');
        }
        return (SELF::$_clientID && SELF::$_clientSecret);
    }

    public static function getTYpe() {
        return SCART_ADDON_TYPE_PROXY_SERVICE_API;
    }

    private static $_lastsuccess = false;
    private static $_lastmessage = '';
    private static $_lastrealIP = '';

    static function responseArray($result,$var) {
        // return first element of $var array if found else false
        $return = false;
        if (is_object($result)) {
            if (property_exists($result,$var) ) {
                if (is_array($result->$var)) {
                    if (count($result->$var) > 0) {
                        $return = $result->$var[0];
                    }
                }
            }
        }
        //scartLog::logLine("D-responseArray(result,$var)=" . print_r($return, true) );
        return $return;
    }
    static function responseBoolean($result,$var) {
        // return value of boolean if found else false
        $return = false;
        if (is_object($result)) {
            if (property_exists($result,$var) ) {
                if (is_bool($result->{$var})) {
                    $return = $result->{$var};
                }
            }
        }
        //scartLog::logLine("D-responseBoolean(result,$var)=$return");
        return $return;
    }

    static function handleCloudflareResponse($result) {

        SELF::$_lastsuccess = false;
        SELF::$_lastmessage = SELF::$_lastrealIP = '';

        $result = json_decode($result);

        //scartLog::logLine("D-cloudflareProxyservice; handleCloudflareResponse result: " . print_r($result,true) );

        if ($succes = SELF::responseBoolean($result,'success')) {

            /**
             * We only want to know if it is using cloudflare and the real IPV4 or IPV6 address
             * The rest of the result codes (not found, not using, not origin found, etc.) is not used.
             *
             */

            // call is successfull
            SELF::$_lastsuccess = $succes;

            if ($return = SELF::responseArray($result,'result')) {

                if (SELF::responseBoolean($return,'isUsingCloudflare')) {

                    // on cloudflare -> get real IP, first IPv4, then IPv6

                    $originIP = SELF::responseArray($return,'originIPv4');
                    if (!$originIP) {
                        $originIP = SELF::responseArray($return,'originIPv6');
                    }
                    if ($originIP && filter_var($originIP, FILTER_VALIDATE_IP, [FILTER_FLAG_IPV4, FILTER_FLAG_IPV6])) {
                        SELF::$_lastrealIP = $originIP;
                        SELF::$_lastmessage = "got real IP from cloudflare ";
                    }

                }

            }

        }

        // always test errors array (can also be set when success=true)

        if ($error = SELF::responseArray($result,'errors') ) {
            $code = (isset($error->code)) ? $error->code : '?';
            $mess = (isset($error->message)) ? $error->message : '?';
            SELF::$_lastmessage = "[code=$code] $mess";
            // To-Do: check "all requests for current day used"?
        }

        if (SELF::$_lastmessage == '' && !SELF::$_lastsuccess) {
            // unkown status
            SELF::$_lastmessage = "unkown cloudflare response (status); result: " . print_r($result,true);
        }

        return SELF::$_lastsuccess;
    }

    static function callCloudflare($url,$debug=false) {

        $success = $error = false;

        $extra = [
            CURLOPT_HTTPHEADER => [
                'CF-Access-Client-ID: '.SELF::$_clientID,
                'CF-Access-Client-Secret:'.SELF::$_clientSecret,
            ],
            CURLOPT_HEADEROPT => CURLHEADER_UNIFIED,
        ];

        if ($debug) scartLog::logLine("D-cloudflareProxyservice; callCURL url='$url' with extra=" . print_r($extra,true) );
        scartLog::logLine("D-cloudflareProxyservice; callCURL url='$url' ");
        $result = scartCURLcalls::call($url,$extra);
        if ($debug) scartLog::logLine("D-cloudflareProxyservice; callCURL result=" . print_r($result,true) );

        $error = scartCURLcalls::hasError();

        if (!$error && $success = SELF::handleCloudflareResponse($result)) {

            scartLog::logLine("D-cloudflareProxyservice; return IP=".SELF::$_lastrealIP.", message=" . SELF::$_lastmessage );
            scartAlerts::alertAdminStatus('CLOUDFLARE_ERROR','cloudflareProxyservice', false);

        } else {

            $errortxt = ($error) ? scartCURLcalls::getError() : SELF::$_lastmessage;
            scartAlerts::alertAdminStatus('CLOUDFLARE_ERROR','cloudflareProxyservice', true, $errortxt, 3, 12);

        }

        return $success;
    }

    public static function getLastError() {

        return ((SELF::$_lastsuccess) ? '' : self::$_lastmessage);
    }

    public static function checkRequirements() {

        $valid = false;
        if (SELF::init()) {
            $url = SELF::$_endpoint;
            // do testing call
            $url = str_replace('{caseID}','test',$url);
            $url .= "?hostname=www.eokm.nl";
            $valid = SELF::callCloudflare($url);
        } else {
            scartLog::logLine("W-cloudflareProxyservice; are ENV vars for cloudflareProxyservice set?! ");
        }
        return $valid;
    }

    /**
     * Get record with url and filenumber
     * Return real IP if found at cloudflare
     * Return false if not found
     *
     * @param $record
     * @return false|string
     */
    public static function run($record,$debug=false) {

        $proxyip = SELF::$_lastsuccess = false;
        SELF::$_lastmessage = '';

        if (SELF::init()) {
            $parsed = parse_url($record->url);
            if ($parsed!==false) {
                $host = (isset($parsed['host']) ? $parsed['host'] : '');
                if ($host) {
                    $caseID = (isset($record->filenumber)) ? $record->filenumber : 'N1234567890';
                    $url = SELF::$_endpoint;
                    $url = str_replace('{caseID}',$caseID,$url);
                    $url .= "?hostname=$host";
                    if (SELF::callCloudflare($url,$debug)) {
                        $proxyip = SELF::$_lastrealIP;
                        SELF::$_lastsuccess = true;
                    }
                } else {
                    SELF::$_lastmessage = "cannot find host from url '$record->url' ";
                }
            } else {
                SELF::$_lastmessage = "not valid (input) url '$record->url' ";
            }
        }
        return $proxyip;
    }


}
