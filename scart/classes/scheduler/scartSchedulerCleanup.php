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
use abuseio\scart\classes\cleanup\scartCleanup;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\iccam\scartImportICCAM;

class scartSchedulerCleanup extends scartScheduler {

    /**
     * Schedule CheckNTD
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
                scartScheduler::setMinMemory('4G');

                // -1- Reset to-scrape input if longer then XX hours waiting for classify
                scartLog::logLine("D-".SELF::$logname."; check reset to scrape");
                $cleanup_grade_timeout =  Systemconfig::get('abuseio.scart::scheduler.cleanup.grade_status_timeout',24);
                $job_records = scartCleanup::cleanupOutOfDate($cleanup_grade_timeout);
                $cnt += count($job_records);
                $report_lines[] = "Rescrape grade timeout count: " . count($job_records);

                // -2- Remove scrape-cache from input/notificatie status_code <> CLASSIFY & SCRAPING
                scartLog::logLine("D-".SELF::$logname."; cleanup scrape cache");
                $scrapecleaned = scartCleanup::cleanupScrapeCache();
                $report_lines[] = "Scrape_cache cleanup count: " . $scrapecleaned;

                // -3- System.log
                scartLog::logLine("D-".SELF::$logname."; cycle system.log");
                $cleanlog = scartCleanup::cleanupSystemlogs();

                // -4- Log orphans
                scartLog::logLine("D-".SELF::$logname."; check orphans");
                $orphancnt = scartCleanup::cleanupOrphan();
                $report_lines[] = "Orphan cleanup count: " . $orphancnt;

                // -5- Cleanup whois cache
                scartLog::logLine("D-".SELF::$logname."; cleanup whois cache");
                $whoiscleaned = scartCleanup::cleanupWhoisCache();
                $report_lines[] = "Whosi cleanup count: " . $whoiscleaned;

                // -6- set day behind to cleanup reports from last day who are missing because of  different timezones (hotlines)
                if (Systemconfig::get('abuseio.scart::scheduler.importexport.iccam_active', false)) {

                    scartLog::logLine("D-".SELF::$logname."; check ICCAM last date");
                    $lastdate = scartImportICCAM::getImportlast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE);
                    if ($lastdate) {

                        // Note: this cleanup job runs each day on 00:05

                        // check if not already in a cleanup sweep...
                        $last = date('Y-m-d', strtotime($lastdate));
                        if ($last === date('Y-m-d')) {
                            $lastlast = $lastdate;
                            // lastdate on current day -> set lastday backwards (at midnight)
                            $lastdate = date('Y-m-d 00:00:00', strtotime('-1 day', strtotime($lastdate)));
                            $report_lines[] = "ICCAM report clean sweep; lastdate was '$lastlast', reset on " . $lastdate;
                            scartImportICCAM::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE, $lastdate);
                        } else {
                            $report_lines[] = "ICCAM report cleanup already running with lastday set on: " . $lastdate;
                        }
                    }

                }

                // ** report
                if (count($job_records) > 0 || $scrapecleaned > 0  || $cleanlog) {
                    $params = [
                        'job_inputs' => $job_records,
                        'scrapecleaned' => $scrapecleaned,
                        'cleanlog' => $cleanlog,
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_cleanup', $params);

                    $params = [
                        'reportname' => 'Cleanup job',
                        'report_lines' => $report_lines
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

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
