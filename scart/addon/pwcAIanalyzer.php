<?php
namespace abuseio\scart\addon;

/**
 * online checker imagetwist
 *
 * Each image has a code that is available in its URL itself.
 *
 * ENVIRONMENT (.env) vars:
 * - ADDON_PWCAI_HOST
 * - ADDON_PWCAI_PORT
 *
 * image url is:
 * 1. alive; https://imagetwist.com/h6yr1fwq90es/ElTMf5yXYAEZcuo.jpg
 * 2. dead; https://imagetwist.com/okc1s0wet2j2/EUFMP_9WAAIfwuq.0.jpg
 *
 * get code between / en /
 * then check this at: https://imagetwist.com/cgi-bin/zapi.cgi?check=<code>
 *
 * examples for image urls:
 * 1. IS FOUND: https://imagetwist.com/cgi-bin/zapi.cgi?check=h6yr1fwq90es
 * 2. NOT FOUND: https://imagetwist.com/cgi-bin/zapi.cgi?check=okc1s0wet2j2
 *
 * Class imagetwistCheckonline
 * @package abuseio\scart\addon
 */

use Config;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\browse\scartCURLcalls;

class pwcAIanalyzer {

    static $_pushurl = 'http://{host}/predict';
    static $_pollurl = 'http://{host}/poll_database';
    static $_polllist = 'http://{host}/poll_database_list';
    static $_lasterror = '';
    static $_curltimeout = 60;

    private static $_pwcai_host = '';
    private static $_pwcai_port = '';

    public static $_resultsuccess = 1;
    public static $_resultnotready = 2;
    private static $_resultcodes = [
        '101' => 'id not found',
        '102' => 'no valid image data',
        '103' => 'can not process image',
    ];

    public static function init() {

        if (!SELF::$_pwcai_host || !SELF::$_pwcai_port) {
            SELF::$_pwcai_host = env('ADDON_PWCAI_HOST', 'pwcai');
            SELF::$_pwcai_port = env('ADDON_PWCAI_PORT', '5051');
        }
        return (SELF::$_pwcai_host && SELF::$_pwcai_port);
    }


    public static function getTYpe() {
        return SCART_ADDON_TYPE_AI_IMAGE_ANALYZER;
    }

    public static function checkRequirements() {
        // requirements easy
        return true;
    }

    public static function getLastError() {

        return self::$_lasterror;
    }

    static function callPwcAI($url,$poststring='') {

        $result = false;

        if (self::init()) {

            $url = str_replace('{host}', SELF::$_pwcai_host, $url);

            if ($poststring) {
                $extra = [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $poststring,
                    CURLOPT_PORT => SELF::$_pwcai_port,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HEADER => false,
                    CURLOPT_CONNECTTIMEOUT => self::$_curltimeout,    // time-out on connect
                    CURLOPT_TIMEOUT => self::$_curltimeout,    // time-out on response
                ];
            } else {
                $extra = [
                    CURLOPT_POST => false,
                    CURLOPT_PORT => SELF::$_pwcai_port,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HEADER => false,
                    CURLOPT_CONNECTTIMEOUT => self::$_curltimeout,    // time-out on connect
                    CURLOPT_TIMEOUT => self::$_curltimeout,    // time-out on response
                ];
            }

            //scartCURLcalls::setDebug(true);

            scartLog::logLine("D-callPwcAI; callCURL url=$url, port=".$extra[CURLOPT_PORT]);
            $result = scartCURLcalls::call($url,$extra,false);

            if (scartCURLcalls::hasError()) {
                self::$_lasterror = scartCURLcalls::getError();
                $result = false;
            } else {
                $result = json_decode($result);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    self::$_lasterror = json_last_error();
                    $result = false;
                }
                //scartLog::logLine("D-callPwcAI; result=" . print_r($result,true) );
            }

            scartCURLcalls::close();


        } else {
            self::$_lasterror = 'error in initialization';
        }

        if (!$result) {
            scartLog::logLine("E-callPwcAI; curl error=" . self::$_lasterror );
        }

        return $result;
    }

    /**
     *
     * action:
     * - push
     * - poll
     *
     * Post:
     * - SCART_ID: SCART reference
     * - (push) image: base64 image string
     *
     * @param $record
     * @return false|mixed|string
     */

    public static function run($record) {

        $result = self::$_lasterror = '';

        try {

            $action = (isset($record['action'])?$record['action']:'');
            $post = (isset($record['post'])?$record['post']:'');

            if ($action) {

                if ($action=='push') {
                    $poststring = http_build_query($post);
                    $result = self::callPwcAI(self::$_pushurl,$poststring);
                    // @TO-DO; action=polls -> POST ids
                } elseif ($action=='poll') {
                    $poststring = urlencode($post);
                    $url = self::$_pollurl . '?id=' . $poststring;
                    $result = self::callPwcAI($url);

                    if (isset($result->Result_code)) {
                        if ($result->Result_code === SELF::$_resultsuccess) {

                            if (isset($result->Attributes)) {
                                $result = (array) $result->Attributes;
                            } else {
                                scartLog::logLine("W-PWCAIanalyzer; empty AI analyze results?!?");
                                $result = [];
                            }

                        } elseif ($result->Result_code === SELF::$_resultnotready) {
                            scartLog::logLine("D-PWCAIanalyzer; AI analyze not yet ready: result_code=" . $result->Result_code );
                            $result = false;
                        } else {
                            self::$_lasterror = (isset(SELF::$_resultcodes[$result->Result_code]))?SELF::$_resultcodes[$result->Result_code]:'unknown result code!?';
                            scartLog::logLine("W-PWCAIanalyzer; AI analyze can not process: " . self::$_lasterror );
                            $result = false;
                        }
                    } else {
                        self::$_lasterror = 'no valid results from poll!?!';
                        scartLog::logLine("W-PWCAIanalyzer; ".self::$_lasterror);
                        $result = false;
                    }

                } elseif ($action=='list') {
                    $url = self::$_polllist;
                    $result = self::callPwcAI($url);
                }

            } else {
                self::$_lasterror = 'action missing';
                scartLog::logLine("W-PWCAIanalyzer; ".self::$_lasterror);
                $result = false;
            }

        } catch (\Exception $err) {

            scartLog::logLine("E-PWCAIanalyzer; Error on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
            $result = false;

        }

        return $result;
    }


}
