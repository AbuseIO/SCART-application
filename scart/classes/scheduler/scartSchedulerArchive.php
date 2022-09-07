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

            $archive_connection =  Systemconfig::get('abuseio.scart::scheduler.archive.database_connection','');
            $archive_time =  Systemconfig::get('abuseio.scart::scheduler.archive.archive_time',7);

            if ($archive_connection) {

                scartLog::logLine("D-".SELF::$logname."; archive_time: $archive_time days ") ;

                scartScheduler::setMinMemory('4G');

                $only_delete = Systemconfig::get('abuseio.scart::scheduler.archive.only_delete',false);

                // archive deleted records
                $job_records = scartArchive::archiveDeletedRecords($archive_connection,$archive_time,$only_delete);

                // archive audittrail
                $job_records = array_merge($job_records, scartArchive::archiveAudittrail($archive_connection,$archive_time,$only_delete) );

            } else {

                scartLog::logLine("E-No archive connection set!?");

            }
            // ** report

            if (count($job_records) > 0 ) {
                $params = [
                    'job_records' => $job_records,
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_archive', $params);
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.scheduler_archive', $params);

            }

        }

        SELF::endScheduler();

        return $cnt;
    }


}
