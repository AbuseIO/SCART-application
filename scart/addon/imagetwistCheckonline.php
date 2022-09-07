<?php
namespace abuseio\scart\addon;

/**
 * online checker imagetwist
 *
 * Each image has a code that is available in its URL itself.
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

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\browse\scartCURLcalls;

class imagetwistCheckonline {

    public static function getTYpe() {
        return SCART_ADDON_TYPE_LINK_CHECKER;
    }

    public static function checkRequirements() {
        // requirements easy
        return true;
    }

    static function calllinkchecker($code) {

        $found = false;

        $extra = [
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
        ];

        $url = 'https://imagetwist.com/cgi-bin/zapi.cgi?check='.urlencode($code);

        scartLog::logLine("D-imagetwistCheckonline; callCURL code=$code, url='$url' ");
        $result = scartCURLcalls::call($url,$extra);

        if (scartCURLcalls::hasError()) {
            scartLog::logLine("E-imagetwistCheckonline; curl error=" . scartCURLcalls::getError() );
        } else {

            /**
             * if empty, then image online
             * if code, then image offline
             *
             */

            //scartLog::logLine("D-Result=" . print_r($result,true) );

            // note; strange response2result setup -> when empty then online... ;(
            if (empty($result)) {
                $found = true;
            }
            scartLog::logLine("D-imagetwistCheckonline; found=" . ($found?'true':'false') );
        }

        return $found;
    }


    public static function run($record) {

        $online = false;

        /**
         * input record
         * check url
         *
         *
         */

        $url = $record->url;
        $code = str_replace('https://imagetwist.com/','',$url);
        $arr = explode('/',$code);
        if (count($arr) > 0) {
            $code = $arr[0];
            //scartLog::logLine("D-code=$code");
            $online = self::calllinkchecker($code);
        }

        return $online;
    }


}
