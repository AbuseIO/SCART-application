<?php
namespace reportertool\eokm\classes;

use Config;
use BackendMenu;
use BackendAuth;
use Log;
use Mail;
use reportertool\eokm\classes\ertLog;

class ertScheduler {

    private static $_loggedin = false;
    public static $logname = '';

    public static function startScheduler($schedulername,$configname) {

        SELF::$logname = 'scheduler'.$schedulername;

        $memory_min =  Config::get('reportertool.eokm::scheduler.scheduler_memory_limit','' );
        $memory_limit = ini_get('memory_limit');
        //ertLog::logLine("D-".SELF::$logname."; memory_limit=$memory_limit" );
        if ($memory_min!='' && $memory_min != $memory_limit) {
            // need memory
            ini_set('memory_limit', $memory_min);
            ertLog::logLine("D-".SELF::$logname."; set memory_limit $memory_min" );
        }

        $debug =  Config::get('reportertool.eokm::scheduler.'.$configname.'.debug_mode',true);
        $audittrail =  Config::get('reportertool.eokm::scheduler.'.$configname.'.audittrail_mode',true);
        if ($debug) {
            // debug
            ertLog::setLogLevel(0);
        } else {
            // Warning
            ertLog::setLogLevel(2);
        }
        if (!$audittrail ) {
            // turn off
            $mod = new ertModel();
            $mod->setAudittrail(false);
        }
        $release = Config::get('reportertool.eokm::release.version', '0.0a') . '-' . Config::get('reportertool.eokm::release.build', 'UNKNOWN');
        ertLog::logLine('D-'.SELF::$logname."; release=$release, debug=". (($debug) ? 'true' : 'false') .", audittrail=" . (($audittrail) ? 'true' : 'false') );

        if (!SELF::$_loggedin) {

            if (!BackendAuth::check() ) {

                //ertLog::logLine("D-scheduleAnalyseInput; login as scheduler user");
                $user = BackendAuth::authenticate([
                    'login' => Config::get('reportertool.eokm::scheduler.login'),
                    'password' => Config::get('reportertool.eokm::scheduler.password'),
                ]);

                // Sign in as a specific user
                BackendAuth::login($user);

            }

            SELF::$_loggedin = (BackendAuth::check());
        }

        return (SELF::$_loggedin);
    }

    public static function endScheduler() {

        if (ertLog::hasError()) {
            ertLog::errorMail(SELF::$logname.": error(s) found");
            ertLog::resetLog();
        }

        // keep loggedin

    }

}
