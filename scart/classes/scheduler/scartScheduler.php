<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\mail\scartAlerts;
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

        scartLog::logMemory(SELF::$logname.' [job_name='.SELF::$jobName.']');

        if (scartLog::hasError()) {
            scartLog::errorMail(SELF::$logname."; error(s) found");
            scartLog::resetLog();
        }

        // mark not active
        scartUsers::setGeneralOption(SELF::$logname,0);

        // check if other scheduler(s) running to long
        self::checkSchedulerRunningTime();

        // keep loggedin

    }

    /**
     * For each scheduler the active (running) state is memorized in the user option table
     * Use the last update time to check if NOT active for more then the SCART_SCHEDULER_MAX_RUNNING_SECS
     * If so, warn (alert) the admin.
     * Ignore when in maintenance
     *
     */
    public static function checkSchedulerRunningTime($debug=false) {

        if (!Systemconfig::get('abuseio.scart::maintenance.mode',false)) {
            // important schedulers
            $schedulers = [
                'schedulerAnalyzeInput' => 'scrape',
                'schedulerCreateReports' => 'createreports',
                'schedulerImportExport' => 'importexport',
                'schedulerSendAlerts' => 'sendalerts',
                'schedulerSendNTD' => 'sendntd',
                'schedulerCheckOnline' => 'checkntd',
            ];
            foreach ($schedulers as $scheduler => $configname) {
                // only if active
                if (Systemconfig::get('abuseio.scart::scheduler.'.$configname.'.active',true)) {
                    if ($scheduler == 'schedulerCheckOnline' && (Systemconfig::get('abuseio.scart::scheduler.checkntd.mode',SCART_CHECKNTD_MODE_CRON)==SCART_CHECKNTD_MODE_REALTIME)) {
                        $scheduler = 'schedulerRealtimeCheckonline';
                    }
                    $scheduleroption = User_options::where('user_id',0)->where('name',$scheduler)->first();
                    // check updated_at timestamp as
                    $runningsecs = ($scheduleroption) ? (time() - strtotime($scheduleroption->updated_at)) : 0;
                    if ($debug) scartLog::logLine("D-$scheduler; last update $runningsecs secs ago");
                    if ($runningsecs > SCART_SCHEDULER_MAX_RUNNING_SECS) {
                        // alert the admin somewhere each (~) half hour about this state
                        $alertcount = count($schedulers) * 30;
                        scartAlerts::alertAdminStatus('RUNNING_TO_LONG_'.$scheduler,'checkSchedulerRunningTime',true, "$scheduler is NOT active for more then $runningsecs secs!?", 3, $alertcount);
                    } else {
                        scartAlerts::alertAdminStatus('RUNNING_TO_LONG_'.$scheduler,'checkSchedulerRunningTime',false);
                    }
                } else {
                    if ($debug) scartLog::logLine("D-$scheduler not active");
                }
            }
        }
    }


}
