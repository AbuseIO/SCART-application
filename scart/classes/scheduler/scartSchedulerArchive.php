<?php
namespace abuseio\scart\classes\scheduler;

use Config;

use Db;
use Illuminate\Database\ConnectionInterface;
use abuseio\scart\Controllers\Startpage;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Input;
use abuseio\scart\models\Log;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\cleanup\scartArchive;
use abuseio\scart\classes\mail\scartAlerts;

class scartSchedulerArchive extends scartScheduler {

    /**
     * Schedule CheckNTD
     *
     * once=false: default check ALL inputs
     * Login scheduler account
     *
     */
    public static function doJob() {

        $cnt = 0;

        if (SELF::startScheduler('Archive','archive')) {

            $job_records= [];

            if (scartArchive::isActiveValid()) {

                // base is year before current year; so at 1-jan one year, at 31-dec almost two years

                $archive_time = Systemconfig::get('abuseio.scart::scheduler.archive.archive_time_offset','-1 years');
                $before = date('Y-01-01 00:00:00', strtotime("$archive_time"));
                scartLog::logLine("D-".SELF::$logname."; archive_time=$archive_time, before time=$before") ;

                scartScheduler::setMinMemory('8G');

                // archive records
                $job_records = scartArchive::archiveRecords($before);

                if (Systemconfig::get('abuseio.scart::scheduler.archive.archive_audittrail',false)) {
                    // archive audittrail records
                    $job_records = array_merge($job_records, scartArchive::archiveAudittrail($before));
                }

                // @TO-DO; may be also do retention with the data with the archive database?
                // Note: if retention is running in the Cleanup scheduler, then records are already anomized in the realtime database before archiving

//                if ($closedRetention = Systemconfig::get('abuseio.scart::scheduler.cleanup.closed_retention', '')) {
//                    scartLog::logLine("D-".SELF::$logname."; make reports fields anonymous with CLOSED retention of '$closedRetention'");
//                    $report_lines[] = scartCleanup::cleanupRetention($closedRetention,SELF::$logname,$job_records);
//                }

            } else {

                $status = 'No archive connection set - cannot be active';
                scartLog::logLine("D-".SELF::$logname."; $status");
                $job_records[] = [
                    'tablename' => '(no table)',
                    'count' => 0,
                    'status' => $status,
                ];

            }
            // ** report

            if (count($job_records) > 0 ) {
                $params = [
                    'job_records' => $job_records,
                ];
                //scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_archive', $params);
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.scheduler_archive', $params);
            }

        }

        SELF::endScheduler();

        return $cnt;
    }


}
