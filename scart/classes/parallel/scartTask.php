<?php
namespace abuseio\scart\classes\parallel;

use abuseio\scart\classes\scheduler\scartScheduler;
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

class scartTask {

    private static $_loggedin = false;
    private static $_startTime = '';
    public static $logname = '';
    public static $jobName = '';

    public static function startTask($taskname,$configname) {

        // 2020/8/5/Gs: include PID for process investigation(s)
        SELF::$logname = "scartRealtimeCheckonlineTask[$taskname]";

        SELF::$_loggedin = false;

        $release = Systemconfig::get('abuseio.scart::release.version', '0.0a') . '-' . Systemconfig::get('abuseio.scart::release.build', 'UNKNOWN');

        $memory_limit = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_memory_limit','1G');
        $memory_limit = scartScheduler::setMinMemory($memory_limit);
        scartLog::logLine('D-'.SELF::$logname."; started; release=$release; memory_limit set on $memory_limit");

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
        return (SELF::$_loggedin);
    }

    public static function endTask() {

        if (scartLog::hasError()) {
            scartLog::errorMail(SELF::$logname."; error(s) found");
            scartLog::resetLog();
        }

        scartLog::logLine("D-".SELF::$logname."; stop");

    }


}
