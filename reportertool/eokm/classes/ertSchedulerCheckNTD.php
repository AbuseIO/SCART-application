<?php
namespace reportertool\eokm\classes;

use Config;

use Db;
use Illuminate\Database\ConnectionInterface;
use reportertool\eokm\classes\ertScheduler;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Ntd;
use ReporterTool\EOKM\Models\Ntd_url;
use ReporterTool\EOKM\Models\Abusecontact;

class ertSchedulerCheckNTD extends ertScheduler {

    /**
     * Schedule CheckNTD
     *
     * once=false: default check ALL inputs
     * Login scheduler account
     *
     */
    public static function doJob() {

        $cnt = 0;

        if (SELF::startScheduler('CheckNTD','checkntd')) {

            $job_records = array();

            // config params
            $check_online_every =  Config::get('reportertool.eokm::scheduler.checkntd.check_online_every',60);
            $registrar_interval = Config::get('reportertool.eokm::NTD.registrar_interval',5);
            $scheduler_process_count = Config::get('reportertool.eokm::scheduler.scheduler_process_count',15);

            /** CHECK inputs and notifications**/

            // Find ILLEGAL input with status checkonline and first-time or last_seen is (check_online_every) minutes ago
            $inputs = Input::whereIn('status_code',[ERT_STATUS_SCHEDULER_CHECKONLINE,ERT_STATUS_FIRST_POLICE])
                ->where('grade_code',ERT_GRADE_ILLEGAL)
                ->where(function($query) use ($check_online_every) {
                    $query->where('online_counter', 0)->orWhere(function ($query) use ($check_online_every) {
                        $last1hour = date('Y-m-d H:i:s', strtotime("-$check_online_every minutes"));
                        $query->where('lastseen_at', '<', $last1hour)->where('lastseen_at', '<>', null);
                    });
                })->take($scheduler_process_count)->get();

            if (count($inputs) > 0) {

                ertLog::logLine("D-SchedulerCheckNTD; number of INPUT records to check: " . count($inputs) . "; check_online_every: $check_online_every minute(s)");

                foreach ($inputs AS $record) {

                    $result = [];

                    try {

                        $result = ertCheckNTD::doCheckIllegalOnline($record,$check_online_every,$registrar_interval);

                    } catch (\Exception $err) {
                        ertLog::logLine("E-SchedulerCheckNTD.doCheckOnline exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                    }

                    if (count($result) > 0) {
                        $job_records = array_merge($job_records, $result);
                    }

                    $cnt += 1;

                }

            }

            // Note: again for Notifications -> in de subfunction we have to change/save record -> with a JOIN this is not working -> essential..!

            // Find ILLEGAL notifications (images) with status checkonline and first-time or last_seen is (check_online_every) minutes ago
            $notifications = Notification::whereIn('status_code',[ERT_STATUS_SCHEDULER_CHECKONLINE,ERT_STATUS_FIRST_POLICE])
                ->where('grade_code',ERT_GRADE_ILLEGAL)
                ->where(function($query) use ($check_online_every) {
                    $query->where('online_counter', 0)->orWhere(function ($query) use ($check_online_every) {
                        $last1hour = date('Y-m-d H:i:s', strtotime("-$check_online_every minutes"));
                        $query->where('lastseen_at', '<', $last1hour)->where('lastseen_at', '<>', null);
                    });
                })->get();

            if (count($notifications) > 0) {

                ertLog::logLine("D-SchedulerCheckNTD; number of NOTIFICATION records to check: " . count($notifications) . "; check_online_every: $check_online_every minute(s)");

                foreach ($notifications AS $record) {

                    $result = [];

                    try {

                        $result = ertCheckNTD::doCheckIllegalOnline($record,$check_online_every,$registrar_interval);

                    } catch (\Exception $err) {
                        ertLog::logLine("E-SchedulerCheckNTD.doCheckOnline exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                    }

                    if (count($result) > 0) {
                        $job_records = array_merge($job_records, $result);
                    }

                    $cnt += 1;

                }

            }

            if (count($job_records) > 0) {

                $params = [
                    'job_inputs' => $job_records,
                ];
                ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.scheduler_check_ntd', $params);

            }

        } else {

            ertLog::logLine("E-Error; cannot login as Scheduler");

        }

        SELF::endScheduler();

        return $cnt;
    }


}
