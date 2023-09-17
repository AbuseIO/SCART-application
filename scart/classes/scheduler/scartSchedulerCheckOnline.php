<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\browse\scartBrowser;
use Config;

use Db;
use Illuminate\Database\ConnectionInterface;
use abuseio\scart\models\Input;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_url;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\online\scartCheckOnline;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\helpers\scartUsers;

/**
 * CRON version of Checkonline
 *
 * Check every time if input records are waiting for
 * - first time
 * - retry (got browser or whois error)
 * - normal (
 *
 *
 */


class scartSchedulerCheckOnline extends scartScheduler {

    /**
     * check inputs; doCheckIllegalOnline
     * - if more records then $scheduler_process_count, walk records by $scheduler_process_count
     *
     * @param $checkref
     * @param $inputs
     * @param $scheduler_process_count
     * @param $check_online_every
     * @param $registrar_interval
     * @param $job_records
     * @return int
     */
    static function checkInputs($checkref,$inputs,$scheduler_process_count,$check_online_every,&$job_records) {

        $cnt = 0;
        $cntrecs = $inputs->count();

        if ($cntrecs > 0) {

            // last time record count?
            if ($checkref!='') {
                $lastrec = scartUsers::getOption($checkref.'#lastrec');
                // reset if empty or more then current records
                if (empty($lastrec) || $lastrec > $cntrecs) $lastrec = 0;
                // save new value
                scartUsers::setOption($checkref.'#lastrec', ($lastrec + $scheduler_process_count) );
            } else {
                $lastrec = 0;
            }

            // init/start browser
            scartBrowser::startBrowser();

            scartLog::logLine("D-scartSchedulerCheckOnline; checkref=$checkref; check_online_every $check_online_every minute(s); lastrec: $lastrec, take: $scheduler_process_count, cntrecs: $cntrecs ");

            $cntdone = 0;
            $records = $inputs
                ->skip($lastrec)
                ->take($scheduler_process_count)
                ->get();
            foreach ($records AS $record) {

                $result = [];

                try {

                    $result = scartCheckOnline::doCheckIllegalOnline($record, $cntrecs,($cntdone + 1 + $lastrec),'scheduler');

                } catch (\Exception $err) {
                    scartLog::logLine("E-scartSchedulerCheckOnline exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                }

                if (count($result) > 0) {
                    $job_records = array_merge($job_records, $result);
                }

                $cntdone += 1;

            }

            // stop browser
            scartBrowser::stopBrowser();

            // save last record count
            if ($cntdone == $scheduler_process_count && $lastrec < $cntrecs ) {
                $lastrec += $scheduler_process_count;
            } elseif ($cntdone < $scheduler_process_count) {
                $lastrec = 0;
            }
            scartLog::logLine("D-scartSchedulerCheckOnline; checkref=$checkref; set NEXT record; cntdone=$cntdone, lastrec=$lastrec, cntrecs=$cntrecs ");

            $cnt += $cntdone;
        }

        return $cnt;
    }

    /**
     * Basic query for getting worker records; online check of illegal content
     *
     * FirstTime; first time (online_counter=0)
     * Retry; has browser of whois errors
     * Normal; not first time and no errors
     *
     * lastseen; last seen time
     * lastseenCount; number of records with last seen time before $lookagaintime
     *
     */

    public static function FirstTime() {

        scartLog::logLine("D-scartSchedulerCheckOnline; get FirstTime ");
        return Input::whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,SCART_STATUS_FIRST_POLICE])
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->whereNull('checkonline_lock')
            ->where('online_counter','=', 0)
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->orderBy('id');
    }

    public static function Retry() {

        // min 15mins before retry again
        $last5min = date('Y-m-d H:i:s', strtotime("-15 minutes"));
        scartLog::logLine("D-scartSchedulerCheckOnline; get Retry (updated_at < '$last5min')");
        return Input::whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL])
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->whereNull('checkonline_lock')
            ->where(function($query) {
                $query->where('browse_error_retry', '<>', 0)->orWhere('whois_error_retry', '<>', 0);
            })
            ->where('updated_at','<',$last5min)
            ->orderBy('browse_error_retry','DESC')->orderBy('whois_error_retry','DESC')->orderBy('received_at','ASC');
    }

    public static function Normal($check_online_every=0) {

        return Input::whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL])
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->whereNull('checkonline_lock')
            ->where('online_counter','>',0)
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->where(function($query) use ($check_online_every) {
                if ($check_online_every > 0) {
                    $last1hour = date('Y-m-d H:i:s', strtotime("-$check_online_every minutes"));
                    scartLog::logLine("D-scartSchedulerCheckOnline; get Normal; check_online_every=$check_online_every min; lastseen_at < $last1hour");
                    $query->where('lastseen_at', '<', $last1hour)->orWhereNull('lastseen_at');
                } else {
                    scartLog::logLine("D-scartSchedulerCheckOnline; get Normal");
                }
            });
    }

    public static function countWork($runtime='') {

        // Count every one, first time, normal and retry

        $records = Input::where('grade_code',SCART_GRADE_ILLEGAL);
        if ($runtime=='Normal') {
            $records = $records->whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL])
                ->where('online_counter','>',0)
                ->where('browse_error_retry',0)
                ->where('whois_error_retry',0);
        } elseif ($runtime == 'FirstTime') {
            $records = $records->whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,SCART_STATUS_FIRST_POLICE])
                ->where('online_counter','=',0)
                ->where('browse_error_retry',0)
                ->where('whois_error_retry',0);
        } elseif ($runtime == 'Retry') {
            $records = $records->whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL])
                ->where(function($query) {
                    $query->where('browse_error_retry', '<>', 0)->orWhere('whois_error_retry', '<>', 0);
                });
        } else {
            $records = $records->whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,SCART_STATUS_FIRST_POLICE]);
        }
        return $records->count();
    }


    public static function lastseen() {

        // last seen for cron/realtime job(s)
        return Input::whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE])
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->where('online_counter','>=',0)            // also firsttime
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->whereNotNull('lastseen_at')
            ->orderBy('lastseen_at','ASC')
            ->select('lastseen_at')
            ->first();
    }

    public static function lastseenCount($lookagaintime) {

        // last seen for cron/realtime job(s)
        return Input::whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE])
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->where('online_counter','>=',0)            // also firsttime
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->whereNotNull('lastseen_at')
            ->where('lastseen_at', '<', $lookagaintime)
            ->count();
    }

    public static function lastseenTop10($lookagaintime) {

        // last seen for cron/realtime job(s)
        return Input::whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE])
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->where('online_counter','>=',0)            // also firsttime
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->whereNotNull('lastseen_at')
            ->where('lastseen_at', '<', $lookagaintime)
            ->orderBy('lastseen_at','ASC')
            ->take(10)
            ->get();
    }

    public static function All() {

        return Input::whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL])
            ->where('grade_code',SCART_GRADE_ILLEGAL);
    }

    public static function checkStddevTime($runtime='Normal') {

        // note; FirstTime and Normal same, Retry different
        $records = Input::where('grade_code',SCART_GRADE_ILLEGAL);
        if ($runtime == 'Retry') {
            $records = $records->whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE])
                ->where('checkonline_leadtime','>',0)
                ->where(function($query) {
                    $query->where('browse_error_retry', '<>', 0)->orWhere('whois_error_retry', '<>', 0);
                });
        } else {
            $records = $records->whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE])
                ->where('checkonline_leadtime','>',0)
                ->where('online_counter','>=',0)
                ->where('browse_error_retry',0)
                ->where('whois_error_retry',0);
        }
        $stddev = $records->selectRaw('STDDEV(checkonline_leadtime) AS checkonline_leadtime_stddev')->first();
        return (($stddev) ? $stddev->checkonline_leadtime_stddev : 0);
    }

    public static function checkAvgTime() {

        return Input::where('status_code',SCART_STATUS_SCHEDULER_CHECKONLINE)
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->where('checkonline_leadtime','>',0)
            ->avg('checkonline_leadtime');
    }

    public static function checkMaxTime() {

        return Input::where('status_code',SCART_STATUS_SCHEDULER_CHECKONLINE)
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->where('checkonline_leadtime','>',0)
            ->max('checkonline_leadtime');
    }

    public static function checkMinTime() {

        return Input::where('status_code',SCART_STATUS_SCHEDULER_CHECKONLINE)
            ->where('grade_code',SCART_GRADE_ILLEGAL)
            ->where('browse_error_retry',0)
            ->where('whois_error_retry',0)
            ->where('checkonline_leadtime','>',0)
            ->min('checkonline_leadtime');
    }


    /**
     * Schedule CheckNTD
     *
     * once=false: default check ALL inputs
     * Login scheduler account
     *
     */
    public static function doJob() {

        $cnt = 0;

        $mode =  Systemconfig::get('abuseio.scart::scheduler.checkntd.mode',SCART_CHECKNTD_MODE_CRON);
        if ($mode != SCART_CHECKNTD_MODE_CRON) {
            // note: double check here, check also Plugins
            scartLog::logLine("D-scartSchedulerCheckOnline; checkntd(online) mode is NOT cron - STOP processing");
        } else {

            if (SELF::startScheduler('CheckOnline','checkntd')) {

                $job_records = array();

                // config params
                $check_online_every =  Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',60);

                $scheduler_process_count = Systemconfig::get('abuseio.scart::scheduler.checkntd.scheduler_process_count','');
                if ($scheduler_process_count=='') $scheduler_process_count = Systemconfig::get('abuseio.scart::scheduler.scheduler_process_count',15);
                //scartLog::logLine("D-scartSchedulerCheckOnline; check_online_every=$check_online_every, registrar_interval=$registrar_interval");

                // 10 times more records for online_counter=0 (first NTD)
                $scheduler_process_count0 = ($scheduler_process_count * 10);

                // at start reset all CHECKONLINE locks
                scartCheckOnline::resetAllLocks();

                /**
                 * Check inputs based on first-time or last-seen
                 * Seperated loops; first check first-time (online_counter=0), secondly with errors, last normale records
                 *
                 */

                // FIRST Find ILLEGAL inputs with status checkonline and first-time
                $inputs = self::FirstTime();
                $cnt += self::checkInputs('inputscounter0',$inputs,$scheduler_process_count0,$check_online_every,$job_records);

                // SECONDLY WITH retry browser or whois error
                $inputs = self::Retry();
                $cnt += self::checkInputs('inputslastseenerror',$inputs,$scheduler_process_count,$check_online_every,$job_records);

                // LAST BUT NOT LEAST "NORMAL" ILLEGAL inputs with last_seen is (check_online_every) minutes ago and no retry errors
                $inputs = self::Normal($check_online_every)
                    ->orderBy('received_at','ASC');
                $cnt += self::checkInputs('inputslastseenreceived',$inputs,$scheduler_process_count,$check_online_every,$job_records);

                // report

                if (count($job_records) > 0) {
                    $params = [
                        'job_inputs' => $job_records,
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_check_ntd', $params);
                }


            }

            SELF::endScheduler();

        }

        return $cnt;
    }


}
