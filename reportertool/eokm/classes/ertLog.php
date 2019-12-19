<?php

namespace reportertool\eokm\classes;

/**
 * ertLog
 *
 * Technical logging from action with code
 * Also sending error report
 *
 */

use Config;
use BackendAuth;
use Log;
use Mail;
use reportertool\eokm\classes\ertMail;
use reportertool\eokm\classes\ertUsers;

class ertLog {

    static $_loglines = array();
    static $_hasError = false;
    static $_logLevels = array('D-','I-','W-','E-');
    static $_logLevel = 0;  // 0=Debug, 1=Info, 2=Warning, 3=Error

    public static function setLogLevel($setlevel) {
        SELF::$_logLevel = $setlevel;
    }

    public static function resetLog() {
        SELF::$_loglines = [];
        SELF::$_hasError = false;
    }

    /**
     * logLine(text): text-prefix;
     * 0 (D-)   : debug
     * 1 (I-)   : info
     * 2 (W-)   : warning
     * 3 (E-)   : error
     *
     * @param $text
     *
     */
    public static function logLine($text, $echo=false) {

        // get level
        $level = array_search(substr($text,0,2),SELF::$_logLevels);
        if ($level >= SELF::$_logLevel) {
            // if error set error flag
            if ($level>=3) SELF::$_hasError = true;
            // log depending on level
            if ($level==0) {
                Log::debug($text);
            } elseif ($level==1) {
                Log::info($text);
            } elseif ($level==2) {
                Log::warning($text);
            } elseif ($level>=3) {
                Log::error($text);
            }
            // build own logmessages
            $line = date('Ymd H:i:s').'> '.$text."\n";
            self::$_loglines[] = $line;
            if ($echo) echo $line;
        }
    }

    public static function hasError() {
        return SELF::$_hasError;
    }

    public static function returnLoglines() {

        $lines = '';
        foreach (self::$_loglines AS $logline) {
            $lines .= $logline.CRLF_NEWLINE;
        }
        return $lines;
    }

    /**
     * Log Error mail
     *
     * @param $errorText
     */
    public static function errorMail($errorText, $excep = null, $errorSubject='') {

        $errormail = Config::get('reportertool.eokm::errors.email','support@svsnet.nl');
        $subject = Config::get('reportertool.eokm::errors.domain','') . ' - error: ' . (($errorSubject) ? $errorSubject : $errorText);

        Log::info("I-ertLog.errorMail: to=$errormail, subject=$subject");

        $body = 'Error: '.$errorText.CRLF_NEWLINE;
        $user = ertUsers::getUser();
        if ($user) {
            $body .= 'User: login='.$user->login.', email='.$user->email.CRLF_NEWLINE.CRLF_NEWLINE;
        }

        $body .= "Loglines:".CRLF_NEWLINE;
        $body .= self::returnLoglines();
        $body .= CRLF_NEWLINE.CRLF_NEWLINE;

        // dump traceback
        if ($excep!=null) {
            $body .= "Traceback:".CRLF_NEWLINE.$excep->getTraceAsString();
        }

        ertMail::sendMailRaw($errormail, $subject, $body);
    }



}
