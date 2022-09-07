<?php
namespace abuseio\scart\addon;

use abuseio\scart\classes\browse\scartCURLcalls;
use abuseio\scart\classes\helpers\scartLog;

/**
 * online checker
 *
 * generale https://<domain>/?op=checkfiles
 *
 * Return codes:
 *  FOUND: "<tr><td>[URL]</td><td style="color:green;">Found</td><td>41 KB</td></tr>"
 *  NOT FOUND: "<tr><td>[URL]</td><td style="color:red;">Not found!</td><td></td></tr>"
 *
 * fallback; if url not found, then not-found
 *
 * @package abuseio\scart\addon
 */

class imgCheckfilesCheckonline {

    public static function getTYpe() {
        return SCART_ADDON_TYPE_LINK_CHECKER;
    }

    public static function checkRequirements() {
        // requirements easy
        return true;
    }

    /**
     * 2021/1/25/Gs:
     *
     * Specific implementation of checkonline by filling a webform online and checking the (html) result
     *
     * Return true when url is reported as online
     *
     * @param $webform
     * @param $url
     * @return bool
     */

    static function callWebform($webform,$url) {

        $found = false;

        $extra = [
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS =>
                "op=checkfiles&list=$url",
            CURLOPT_RETURNTRANSFER => true,
        ];

        //scartLog::logLine("D-cloudflareProxyservice; callCURL url='$url' with extra=" . print_r($extra,true) );
        scartLog::logLine("D-imgCheckfilesCheckonline; callCURL webform=$webform, url='$url' ");
        $result = scartCURLcalls::call($webform,$extra);

        if (scartCURLcalls::hasError()) {
            scartLog::logLine("E-imgCheckfilesCheckonline; curl error=" . scartCURLcalls::getError() );
        } else {

            // locate url and check return value

            //scartLog::logLine("D-Result=" . print_r($result,true) );

            $resultline = '';
            $pos = strpos($result,$url);
            if ($pos !== false) {
                $end = strpos($result,'</tr>');
                $resultline = substr($result,$pos,$end - $pos);
                if (strpos($resultline,'style="color:green;">Found') !== false) {
                    $found = true;
                }
            }
            scartLog::logLine("D-imgCheckfilesCheckonline; resultline='$resultline', found=" . ($found?'true':'false') );
        }

        return $found;
    }

    public static function run($record) {

        $online = false;

        /**
         * input record
         * check url
         *
         */

        // example: https://it1.imgtown.net/i/01027/crnnw8v0zcp0_t.jpg

        $online = self::callWebform('https://imgtown.net/?op=checkfiles',$record->url);

        return $online;
    }


}
