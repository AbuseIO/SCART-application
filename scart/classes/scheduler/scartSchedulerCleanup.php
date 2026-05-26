<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_url;
use Db;
use Config;
use abuseio\scart\classes\parallel\scartRealtimeMonitor;
use abuseio\scart\models\Input_history;
use Illuminate\Database\ConnectionInterface;
use abuseio\scart\Controllers\Startpage;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Input;
use abuseio\scart\models\Log;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\cleanup\scartCleanup;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\iccam\scartImportICCAM;
use Illuminate\Support\Facades\Artisan;

class scartSchedulerCleanup extends scartScheduler {

    /**
     * Schedule Cleanup
     *
     * once=false: default check ALL inputs
     * Login scheduler account
     *
     */
    public static function doJob() {

        $cnt = 0;

        if (SELF::startScheduler('Cleanup','cleanup')) {

            Try {

                $report_lines = [];

                // we need memory
                $mem = Systemconfig::get('abuseio.scart::scheduler.cleanup.memory_limit', '4G');
                scartScheduler::setMinMemory($mem);

                // -1- System.log
                scartLog::logLine("D-".SELF::$logname."; cycle system.log");
                $cleanlog = scartCleanup::cleanupSystemlogs(SELF::$logname);

                // new system (FRESH)log file created

                // -2- Reset to-scrape input if longer then XX hours waiting for classify
                scartLog::logLine("D-".SELF::$logname."; check reset to scrape");
                $cleanup_grade_timeout =  Systemconfig::get('abuseio.scart::scheduler.cleanup.grade_status_timeout',24);
                $job_records = scartCleanup::cleanupOutOfDate($cleanup_grade_timeout,SELF::$logname);
                $cnt += count($job_records);
                $report_lines[] = "Rescrape grade timeout count: " . count($job_records);

                // -3- Remove scrape-cache from input/notificatie status_code <> CLASSIFY & SCRAPING
                scartLog::logLine("D-".SELF::$logname."; cleanup scrape cache");
                $scrapecleaned = scartCleanup::cleanupScrapeCache(SELF::$logname);
                $report_lines[] = "Scrape_cache cleanup count: " . $scrapecleaned;

                // -4- Log orphans
// @TODO; analyze orphans more because of new archive job
//                scartLog::logLine("D-".SELF::$logname."; check orphans");
//                $orphancnt = scartCleanup::cleanupOrphan(SELF::$logname);
//                $report_lines[] = "Orphan count: " . $orphancnt;

                // -5- Cleanup whois cache
                scartLog::logLine("D-".SELF::$logname."; cleanup whois cache");
                $whoiscleaned = scartCleanup::cleanupWhoisCache(SELF::$logname);
                $report_lines[] = "WhoIs cleanup count: " . $whoiscleaned;

                // -6- rewind 1 day to cleanup reports from last day who are missing because of  different timezones (hotlines)
                if (scartICCAMinterface::isActive()) {
                    scartLog::logLine("D-".SELF::$logname."; check ICCAM last date");
                    $report_lines[] = scartICCAMinterface::rewindLastdate(SELF::$logname);
                }

                // -7- if realtime then log realtime workers state
                if (scartRealtimeMonitor::realtimeActive()) {
                    scartLog::logLine("D-".SELF::$logname."; sent realtime status report to admin");
                    scartRealtimeMonitor::sendRealtimeStatusAdmin(SELF::$logname);
                }

                // -8- retention (anonymous) time
                if ($closedRetention = Systemconfig::get('abuseio.scart::scheduler.cleanup.closed_retention', '')) {
                    scartLog::logLine("D-".SELF::$logname."; make reports fields anonymous with CLOSED retention of '$closedRetention'");
                    $report_lines[] = scartCleanup::cleanupRetention($closedRetention,SELF::$logname,$job_records);
                }

                // -9- deleted records
                if ($deleted_records = Systemconfig::get('abuseio.scart::scheduler.cleanup.deleted_records', '')) {
                    scartLog::logLine("D-".SELF::$logname."; cleanup deleted records older then '$deleted_records'");
                    $report_lines[] = scartCleanup::cleanupDeleted($deleted_records,SELF::$logname,$job_records);
                }

                // ** report
                if (count($job_records) > 0 || $scrapecleaned > 0  || $cleanlog) {

                    if (count($job_records) > 0) {
                        $params = [
                            'job_inputs' => $job_records,
                            'scrapecleaned' => $scrapecleaned,
                            'cleanlog' => $cleanlog,
                        ];
                        scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_cleanup', $params);
                    }

                    if (count($report_lines) > 0) {
                        $params = [
                            'reportname' => 'Cleanup job',
                            'report_lines' => $report_lines
                        ];
                        scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
                    }

                }

            } catch(\Exception $err) {

                // NB: \Expection is important, else not in this catch when error in Mail
                scartLog::logLine("E-".SELF::$logname." error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

            }

        }

        SELF::endScheduler();

        return $cnt;
    }




}
