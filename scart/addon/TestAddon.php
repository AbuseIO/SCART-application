<?php
namespace abuseio\scart\addon;

use abuseio\scart\classes\browse\scartCURLcalls;
use abuseio\scart\classes\helpers\scartLog;

/**
 * TestAddon
 *
 * Dummy addon for testing
 *
 */

class TestAddon {

    public static function init() {

        return true;
    }

    public static function getType() {
        //return SCART_ADDON_TYPE_NTDAPI;
        //return SCART_ADDON_TYPE_LINK_CHECKER;
        return SCART_ADDON_TYPE_AI_IMAGE_ANALYZER;
    }

    private static $_lastsuccess = false;
    private static $_lastmessage = '';

    static function call($record) {

        SELF::$_lastsuccess = true;
        SELF::$_lastmessage = '';

        scartLog::logLine("D-TestAddon.call; addon type=".SELF::getType()."; record=" . print_r($record, true)) ;

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
            $report = SELF::call($record);
        }
        return $report;
    }


}
