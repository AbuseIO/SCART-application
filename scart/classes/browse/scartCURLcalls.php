<?php
namespace abuseio\scart\classes\browse;

/**
 *
 * Function
 * - open -> open curl channel
 * - init -> set option with url
 * - call -> curl exec with result check
 * - isOffline -> curl offline state
 * - close -> curl close channel
 *
 *
 * Class scartCURLcalls
 * @package abuseio\scart\classes
 */

use abuseio\scart\classes\helpers\scartLog;

class scartCURLcalls {

    private static $_debug = false;

    private static $_useragent = 'scartCURLcalls';
    private static $_channel = '';
    private static $_curltimeout = 20;
    private static $_curlerror = '';

    public static function setDebug($debug)
    {
        self::$_debug = $debug;
    }

    public static function open()
    {
        if (self::$_channel == '') {
            self::$_channel = curl_init();
        }
    }

    public static function close()
    {
        if (self::$_channel != '') {
            curl_close(self::$_channel);
            self::$_channel = '';
        }
    }

    public static function init($url,$extraoptions = [])
    {

        if (self::$_channel === '') {
            self::open();
        } else {
            // needed, else 'hanging'
            curl_reset(self::$_channel);
        }

        $options = array(
            CURLOPT_RETURNTRANSFER => true,   // return web page
            CURLOPT_HEADER => false,  // don't return headers
            CURLOPT_FOLLOWLOCATION => true,   // follow redirects
            CURLOPT_MAXREDIRS => 10,     // stop after 10 redirects
            CURLOPT_ENCODING => "",     // handle compressed
            CURLOPT_USERAGENT => self::$_useragent, // name of client
            CURLOPT_AUTOREFERER => true,   // set referrer on redirect
            CURLOPT_CONNECTTIMEOUT => self::$_curltimeout,    // time-out on connect
            CURLOPT_TIMEOUT => self::$_curltimeout,    // time-out on response
            CURLOPT_PORT => '443',
            CURLOPT_URL => $url,
        );
        // nummeric keys -> array_merge is not usable because of NUMERIC key values overruling options from extraoptions
        //$options = (count($extraoptions) > 0) ? $options = array_merge($options,$extraoptions) : $options;
        if (count($extraoptions) > 0) {
            foreach ($extraoptions AS $key => $extraoption) {
                $options[$key] = $extraoption;
            }
        }
        if (self::$_debug) scartLog::logLine("D-scartCURLcalls; set curloptions=". print_r($options,true) );
        curl_setopt_array(self::$_channel, $options);
    }

    /**
     * call CURL
     *
     * url: url
     * extraoptions: optional overruling extra CURL options (array)
     *
     * @param string $url
     * @param array $extraoptions
     * @return mixed|bool|string
     */
    public static function call($url='',$extraoptions = [],$debug=false) {

        $result = false;

        if ($url) {

            self::init($url,$extraoptions);

            try {

                self::$_curlerror = '';

                $result = curl_exec(self::$_channel);

                if (self::$_debug || $debug) {
                    $info = curl_getinfo(self::$_channel);
                    scartLog::logLine("D-scartCURLcalls; info=".print_r($info,true));
                }

                if ($result === false) {
                    // Note: if CURL_EXEC error then network level, not application -> handle as offline
                    self::$_curlerror = (curl_errno(self::$_channel) > 0) ? "curl_errorno: " . curl_errno(self::$_channel) . ", error: " . curl_error(self::$_channel) : curl_getinfo(self::$_channel);
                    scartLog::logLine("W-scartCURLcalls; CURL read/update (offline) error: " . self::getError() );

                } else {
                    self::$_curlerror = '';
                }

            } catch (\Exception $ex) {
                self::$_curlerror = 'call exception: line=' .$ex->getLine() . ', message=' . $ex->getMessage();
                scartLog::logLine('E-scartCURLcalls.call error; ' . self::$_curlerror);
            }

        } else {
            self::$_curlerror = "call with empty url!?";
        }

        return $result;
    }

    public static function hasError() {
        return (self::$_curlerror!=='');
    }

    public static function getError() {
        return (is_array(self::$_curlerror)) ? print_r(self::$_curlerror, true) : self::$_curlerror;
    }



}
