<?php
namespace abuseio\scart\classes\scheduler;

use Config;
use BackendMenu;
use BackendAuth;
use Log;
use Mail;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\base\scartModel;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\User_options;

class scartScheduler {

    private static $_loggedin = false;
    private static $_startTime = '';
    public static $logname = '';
    public static $jobName = '';

    public static function setMinMemory($memory_min='') {
        if ($memory_min=='') $memory_min =  Systemconfig::get('abuseio.scart::scheduler.scheduler_memory_limit','' );
        $memory_limit = ini_get('memory_limit');
        if ($memory_min!='' && $memory_min > $memory_limit) {
            // need memory
            ini_set('memory_limit', $memory_min);
        }
        return $memory_min;
    }

    public static function startScheduler($schedulername,$configname) {

        // 2020/8/5/Gs: include PID for process investigation(s)
        SELF::$logname = "scheduler{$schedulername}";

        SELF::$_loggedin = $maintenance = false;

        SELF::$jobName = Systemconfig::get('abuseio.scart::scheduler.job_name','?');

        // check if active (also maintenance mode)
        $active =  Systemconfig::get('abuseio.scart::scheduler.'.$configname.'.active',true);
        if ($active) {
            $active = $maintenance = (!Systemconfig::get('abuseio.scart::maintenance.mode',false));
        }

        // mark active or not
        scartUsers::setGeneralOption(SELF::$logname,($active)?1:0);

        if ($active) {

            self::$_startTime = microtime(true);

            $memory_min = self::setMinMemory();

            $debug =  Systemconfig::get('abuseio.scart::scheduler.'.$configname.'.debug_mode',true);
            // default $audittrail=false
            $audittrail =  Systemconfig::get('abuseio.scart::scheduler.'.$configname.'.audittrail_mode',false);
            if ($debug) {
                // debug
                scartLog::setLogLevel(0);
            } else {
                // Warning
                scartLog::setLogLevel(2);
            }
            if (!$audittrail ) {
                // turn off
                $mod = new scartModel();
                $mod->setAudittrail(false);
            }

            $release = Systemconfig::get('abuseio.scart::release.version', '0.0a') . '-' . Systemconfig::get('abuseio.scart::release.build', 'UNKNOWN');

            scartLog::logLine('D-'.SELF::$logname." [job_name=".SELF::$jobName."]; release=$release, memory_limit=$memory_min, debug=". (($debug) ? 'true' : 'false') .", audittrail=" . (($audittrail) ? 'true' : 'false') );

            if (!SELF::$_loggedin) {

                if (!BackendAuth::check() ) {

                    //scartLog::logLine("D-scheduleAnalyseInput; login as scheduler user");
                    $user = BackendAuth::authenticate([
                        'login' => Systemconfig::get('abuseio.scart::scheduler.login'),
                        'password' => Systemconfig::get('abuseio.scart::scheduler.password'),
                    ]);

                    // Sign in as a specific user
                    BackendAuth::login($user);

                }

                SELF::$_loggedin = (BackendAuth::check());

                if (!SELF::$_loggedin) {
                    scartLog::logLine("E-Error; cannot login as Scheduler");
                }

            }

        } else {
            scartLog::logLine('D-'.SELF::$logname.' [job_name='.SELF::$jobName.']; NOT active ' . (($maintenance) ? '(MAINTENANCE MODE)' : '')  );
        }

        return (SELF::$_loggedin);
    }

    public static function endScheduler() {

        if (self::$_startTime!='') {
            $time_end = microtime(true);
            //dividing with 60 will give the execution time in minutes otherwise seconds
            $execution_time = round(($time_end - self::$_startTime), 1);
            scartLog::logLine('D-'.SELF::$logname.' [job_name='.SELF::$jobName.']; executing time=' . $execution_time . ' secs');
        }

        scartLog::logMemory(SELF::$logname);

        if (scartLog::hasError()) {
            scartLog::errorMail(SELF::$logname."; error(s) found");
            scartLog::resetLog();
        }

        // mark not active
        scartUsers::setGeneralOption(SELF::$logname,0);

        // keep loggedin

    }

    // ** OBSOLUTE** //

    public static function acquireBlock($key) {

        // not working
        return true;

        $semaphore = sem_get($key, 1, 0666, 1);
        if ($semaphore) {
            $set = sem_acquire($semaphore,true);
            if ($set) {
                scartLog::logLine("D-".SELF::$logname."; semaphore set");
            } else {
                scartLog::logLine("W-".SELF::$logname."; semaphore already set");
            }
        } else {
            // continue
            $set = true;
            scartLog::logLine("W-".SELF::$logname."; error set semaphore");
        }
        return $set;
    }

    public static function releaseBlock($key) {

        return true;


        try {
            $semaphore = sem_get($key, 1, 0666, 1);
            sem_release($semaphore);
        } catch (\Exception $err) {
            scartLog::logLine("E-Schedule exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
        }
    }


}
