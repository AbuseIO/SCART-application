<?php

namespace abuseio\scart\classes\helpers;

/**
 * scartLog
 *
 * Technical logging from action with code
 * Also sending error report
 *
 */

use abuseio\scart\Controllers\Police;
use abuseio\scart\models\User_options;
use Config;
use BackendAuth;
use Illuminate\Support\Facades\Log;
use Mail;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Systemconfig;

class scartLog {

    static $_loglines = [];
    static $_hasError = false;
    static $_errLines = [];
    static $_logLevels = array('D-','I-','W-','E-');
    static $_logLevel = 0;  // 0=Debug, 1=Info, 2=Warning, 3=Error
    static $_echo = false;

    public static function setLogLevel($setlevel) {
        SELF::$_logLevel = $setlevel;
    }

    public static function resetLog() {
        SELF::$_loglines = [];
        SELF::$_hasError = false;
    }

    public static function setEcho($echo) {
        SELF::$_echo = $echo;
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
            // add pid for reference
            $pid = getmypid(); if (!$pid) $pid = '(unknown)';
            $text = sprintf('[%07d] ',$pid) . $text;
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
            if ($echo || SELF::$_echo) echo $line;
            // if error set error flag
            if ($level>=3) {
                SELF::$_hasError = true;
                SELF::$_errLines[] = $line;
            }
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

    public static function returnErrlines() {

        $lines = '';
        foreach (self::$_errLines AS $errline) {
            $lines .= $errline.CRLF_NEWLINE;
        }
        return $lines;
    }

    /**
     * Log Error mail
     *
     * @param $errorText
     */
    public static function errorMail($errorText, $excep = null, $errorSubject='', $errorclear=true) {

        $errormail = Systemconfig::get('abuseio.scart::errors.email','support@svsnet.nl');
        $subject = Systemconfig::get('abuseio.scart::errors.domain','') . ' - error: ' . (($errorSubject) ? $errorSubject : $errorText);

        Log::info("D-scartLog.errorMail: to=$errormail, subject=$subject");

        $skip = false;

        try {

            // Detect error-mail-looping errors - skip if more then (max_error_pm) in 1 minute and wait for (pauze_errors_min) minutes

            // Note: looks like a lot of code each time, but when we are here we have an error and so we have time to handle this carefully

            // get config settings
            $maxerrorpm = Systemconfig::get('abuseio.scart::errors.max_error_pm',10);
            $pauze_in_mins = Systemconfig::get('abuseio.scart::errors.pauze_errors_min',10);
            // get current status
            $currenterrors = scartUsers::getGeneralOption('CURRENT_ERROR_MAILS');
            if (!is_array($currenterrors)) $currenterrors = [];
            if (count($currenterrors) != 3) { $currenterrors = [0,time(),0]; }
            list($errors,$lasttime,$mintowait) = $currenterrors;
            $errors += 1;
            // get difference in minute between last error call
            $diffmin = intval((time() - $lasttime) / 60);     // 0 - 1,4999 minutes
            Log::info("D-scartLog.errorMail: errors=$errors, diffmin=$diffmin, mintowait=$mintowait; max_errors_pm=$maxerrorpm, pauze_in_mins=$pauze_in_mins");
            if ($mintowait > 0) {
                // we are in waiting mode
                if ($diffmin <= $mintowait) {
                    // skip this error (mail)
                    $skip = true;
                    Log::warning("W-TO MANY ERRORS (=$errors) WITHIN ONE MINUTE; wait ".($mintowait - $diffmin)." minutes for sending again");
                } else {
                    // reset, we done waiting
                    $mintowait = $errors = 0;
                    // new time offset
                    $lasttime = time();
                }
            } else {
                // check if looping
                if (($errors >= $maxerrorpm) && ($diffmin <= 1)) {
                    // to many detected -> set mins to wait
                    $mintowait = $pauze_in_mins;
                    // log waiting
                    Log::warning("W-TO MANY ERRORS (=$errors) WITHIN ONE MINUTE; wait $mintowait minutes for sending again");
                } elseif ($diffmin > 1) {
                    // more then 1 minute ago -> reset #errors
                    $errors = 0;
                    // new time offset
                    $lasttime = time();
                }
            }
            // save current status
            $currenterrors = [$errors,$lasttime,$mintowait];
            scartUsers::setGeneralOption('CURRENT_ERROR_MAILS', $currenterrors);

        } catch (Exception $err) {
            Log::error("E-scartLog.errorMail: Error online ".$err->getLine().", message ".$err->getMessage() );
        }

        if (!$skip) {

            $body = 'Error: '.$errorText.CRLF_NEWLINE;
            $user = scartUsers::getUser();
            if ($user) {
                $body .= 'User: login='.$user->login.', email='.$user->email.CRLF_NEWLINE;
            }
            $appurl = env('APP_URL', '(unknown)');
            if ($user) {
                $body .= 'App: url='.$appurl.CRLF_NEWLINE;
            }
            $body .= 'Error lines:'.CRLF_NEWLINE;
            $body .= self::returnErrlines();
            $body .= CRLF_NEWLINE.CRLF_NEWLINE;

            $body .= "Loglines:".CRLF_NEWLINE;
            $body .= self::returnLoglines();
            $body .= CRLF_NEWLINE.CRLF_NEWLINE;

            // dump traceback
            if ($excep!=null) {
                $body .= "Traceback:".CRLF_NEWLINE.$excep->getTraceAsString();
            }

            scartMail::sendMailRaw($errormail, $subject, $body);

        }

        if ($errorclear) {
            // reset logging & error status
            self::$_loglines = [];
            self::$_errLines = [];
            self::$_hasError = false;
        }

    }

    /**
     * SCART processes are memory intensive because of the handling of images (videos) data
     * Monitoring is important for tuning environment.
     *
     */

    static function convert2kb($formattedBytes) {

        try {
            $val = floatval(str_replace(['KB','K','MB','M','GB','G'],'',$formattedBytes));
            if (strpos($formattedBytes,'M')!==false) {
                $val = $val * 1000;
            }
            if (strpos($formattedBytes,'G')!==false) {
                $val = $val * 1000000;
            }
        } catch (\Exception $err) {
            scartLog::logLine("W-scartLog.convert2kb($formattedBytes) error: " . $err->getMessage());
            $val = 0;
        }
        return $val;
    }

    static function compareMemval($new,$val) {
        return (self::convert2kb($new) > self::convert2kb($val));
    }

    public static function logMemory($processname='') {

        if ($processname=='') $processname = 'scartNoNameProcess';

        // log
        $mem = round(memory_get_usage(true)/1000000,1).'MB';
        $mempeak = round(memory_get_peak_usage(true)/1000000,1).'MB';
        $memlimit = ini_get('memory_limit');
        self::logLine("D-$processname; Memory=$mem, memory_peak=$mempeak, memory_limit=$memlimit");

        // save for monitoring

        $memory_values = scartUsers::getGeneralOption($processname.'_memory',[]);
        if (isset($memory_values['max_memory'])) {

            if (self::compareMemval($mem,$memory_values['max_memory'])) {
                $memory_values['max_memory'] = $mem;
                $memory_values['max_memory_timestamp'] = date('Y-m-d H:i:s');
            }
            if (self::compareMemval($mempeak,$memory_values['max_peak'])) {
                $memory_values['max_peak'] = $mempeak;
                $memory_values['max_peak_timestamp'] = date('Y-m-d H:i:s');
            }
            if (self::compareMemval($memlimit,$memory_values['max_limit'])) {
                $memory_values['max_limit'] = $memlimit;
                $memory_values['max_limit_timestamp'] = date('Y-m-d H:i:s');
            }
            $memory_values['current_memory'] = $mem;
            $memory_values['current_peak'] = $mempeak;
            $memory_values['current_limit'] = $memlimit;
            $memory_values['current_timestamp'] = date('Y-m-d H:i:s');

        } else {

            $memory_values = [
                'max_memory' => $mem,
                'max_memory_timestamp' => date('Y-m-d H:i:s'),
                'max_peak' => $mempeak,
                'max_peak_timestamp' => date('Y-m-d H:i:s'),
                'max_limit' => $memlimit,
                'max_limit_timestamp' => date('Y-m-d H:i:s'),
                'current_memory' => $mem,
                'current_peak' => $mempeak,
                'current_limit' => $memlimit,
                'current_timestamp' => date('Y-m-d H:i:s'),
            ];

        }
        scartUsers::setGeneralOption($processname.'_memory',$memory_values);
        //self::logLine("D-$processname; memory values: " . print_r($memory_values,true));

    }

    public static function reportLogMemory() {

        $headers = [
            'process name','peak update','peak(max)','memory(max) update','memory(max)','limit(max) update','limit(max)','current update','peak(current)','memory(current)','limit(current)',
        ];

        function val($arr,$ind) {
            $val = (isset($arr[$ind])?$arr[$ind]:'');
            return $val;
        }

        $lines = [];
        $process_limits = User_options::where('user_id',0)->where('name','LIKE','%_memory')->orderBy('name')->get();
        foreach ($process_limits AS $process_limit) {
            $procesname = str_replace('_memory','',$process_limit->name);
            $valuearr = unserialize($process_limit->value);
            $line = [
                $procesname,
                val($valuearr,'max_peak_timestamp'),
                val($valuearr,'max_peak'),
                val($valuearr,'max_memory_timestamp'),
                val($valuearr,'max_memory'),
                val($valuearr,'max_limit_timestamp'),
                val($valuearr,'max_limit'),
                val($valuearr,'current_timestamp'),
                val($valuearr,'current_peak'),
                val($valuearr,'current_memory'),
                val($valuearr,'current_limit'),
            ];
            $lines[] = $line;
        }

        return ['headers' => $headers,'lines' => $lines];
    }



}
