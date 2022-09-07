<?php
namespace abuseio\scart\addon;

use abuseio\scart\classes\browse\scartCURLcalls;
use abuseio\scart\classes\helpers\scartLog;

/**
 * NTD API IP volumen
 *
 * https://childporn.report/api/doc/reports
 *
 * ENVIRONMENT (.env) vars:
 * - ADDON_NTDAPI_IPVOLUME_TOKEN
 *
 * @package abuseio\scart\addon
 */

class IPvolumeNTDapi {

    private static $_endpoint = 'https://childporn.report/api/v1/reports';

    private static $_BearerToken = '';

    public static function init() {

        if (!SELF::$_BearerToken) {
            SELF::$_BearerToken = env('ADDON_NTDAPI_IPVOLUME_TOKEN', '');
        }
        return (SELF::$_BearerToken);
    }

    public static function getTYpe() {
        return SCART_ADDON_TYPE_NTDAPI_IPVOLUME;
    }

    private static $_lastsuccess = false;
    private static $_lastmessage = '';

    static function callNTDAPI($record) {

        SELF::$_lastsuccess = false;
        SELF::$_lastmessage = '';

        $data = [
            'ip' => $record->url_ip,
            'urls' => $record->url,
        ];
        //$postfields = "ip=$record->url_ip;urls=$record->url\n";
        $postfields = http_build_query($data);

        $extra = [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.SELF::$_BearerToken,
            ],
            CURLOPT_HEADEROPT => CURLHEADER_UNIFIED,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postfields,
        ];

        scartLog::logLine("D-callNTDAPI; callCURL postfields='$postfields' ");
        $result = scartCURLcalls::call(SELF::$_endpoint,$extra);

        if (scartCURLcalls::hasError()) {
            scartLog::logLine("E-callNTDAPI; curl error=" . scartCURLcalls::getError() );
        } else {
            //scartLog::logLine("D-callNTDAPI; result=".print_r($result,true));

            $data = json_decode($result);
            //scartLog::logLine("D-callNTDAPI; data=".print_r($data,true));
            if (isset($data->message) &&  $data->message!='') {
                // error
                SELF::$_lastmessage = "message=$data->message, errors=" . print_r($data->errors,true);
                scartLog::logLine("E-callNTDAPI; error=".SELF::$_lastmessage );
            } else {
                scartLog::logLine("D-callNTDAPI; report created with id=".$data->data->id );
                SELF::$_lastsuccess = true;
            }

        }

        return SELF::$_lastsuccess;
    }

    public static function getLastError() {

        return ((SELF::$_lastsuccess) ? '' : self::$_lastmessage);
    }

    public static function checkRequirements() {

        return true;
    }

    /**
     * Get record with url and filenumber
     * Return real IP if found at cloudflare
     * Return false if not found
     *
     * @param $record
     * @return false|string
     */
    public static function run($record) {

        if (SELF::init()) {
            $report = SELF::callNTDAPI($record);
        }
        return $report;
    }


}
