<?php
namespace reportertool\eokm\classes;

use Config;

use Db;
use Illuminate\Database\ConnectionInterface;
use reportertool\eokm\classes\ertScheduler;
use ReporterTool\EOKM\Models\Grade_answer;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Log;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_input;
use ReporterTool\EOKM\Models\Notification_selected;
use ReporterTool\EOKM\Models\Scrape_cache;

class ertSchedulerCleanup extends ertScheduler {

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

            $job_records = array();

            // -1- reset SCRAPPED input if longer then XX hours waiting for classify

            $cleanup_grade_timeout =  Config::get('reportertool.eokm::scheduler.cleanup.grade_status_timeout',24);
            $timeout = date('Y-m-d H:i:s', strtotime("-$cleanup_grade_timeout hours"));

            // no limit -> each night one time
            //$scheduler_process_count = Config::get('reportertool.eokm::scheduler.scheduler_process_count',15);

            // last time updated good indication of last-time worked at
            ertLog::logLine("D-schedulerCleanup: check if classify inputs older then: $timeout");
            $inputs = Input::where('status_code',ERT_STATUS_GRADE)
                ->where('updated_at', '<', $timeout)
                ->get();

            foreach ($inputs AS $input) {

                // in Input::beforeDelete we handle the delete of the foreign data
                $status = "Input $input->filenumber longer then $cleanup_grade_timeout hours waiting for classify" ;

                $lock = ertGrade::getLock($input->id);
                if ($lock!='') {

                    $locked_workuser = ertUsers::getFullName($lock->workuser_id);
                    $status .= "; SKIP - input locked by=$lock->workuser_id, fullname=$locked_workuser";
                    //ertLog::logLine("D-$status");

                 } else {

                    // stop processing
                    $input->status_code = ERT_STATUS_OPEN;
                    $input->save();

                    try {

                        $input->deleteRelated();

                    } catch (\Exception $err) {
                        ertLog::logLine("E-Cleanup: exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                    }

                    // mark for scrape
                    $input->status_code = ERT_STATUS_SCHEDULER_SCRAPE;
                    $input->save();

                    $status .= "; reset - set for new scrape";
                    $input->logText($status);

                    ertLog::logLine("D-schedulerCleanup; $status");
                    $job_records[] = [
                        'filenumber' => $input->filenumber,
                        'url' => $input->url,
                        'status' => $status,
                    ];

                    $cnt += 1;

                }

            }

            // -2- remove scrape-cache from input/notificatie status_code <> CLASSIFY & SCRAPING

            //trace_sql();
            /*
            $scraped = Scrape_cache::join(ERT_NOTIFICATION_TABLE,ERT_NOTIFICATION_TABLE.'.url_hash','=',ERT_SCRAPE_CACHE_TABLE.'.code')
                ->whereNull(ERT_NOTIFICATION_TABLE.'.deleted_at')
                ->whereNotIn(ERT_NOTIFICATION_TABLE.'.status_code',[ERT_STATUS_GRADE,ERT_STATUS_SCHEDULER_SCRAPE])
                ->select(ERT_NOTIFICATION_TABLE.'.id')
                ->get();
            */

            //trace_sql();
            /*
            $scraped = Input::whereNotIn(ERT_INPUT_TABLE.'.status_code',[ERT_STATUS_GRADE,ERT_STATUS_SCHEDULER_SCRAPE])
                ->join('reportertool_eokm_notification_input', 'reportertool_eokm_notification_input.input_id', '=', ERT_INPUT_TABLE.'.id')
                ->join(ERT_NOTIFICATION_TABLE, ERT_NOTIFICATION_TABLE.'.id', '=', 'reportertool_eokm_notification_input'.'.notification_id')
                ->join(ERT_SCRAPE_CACHE_TABLE,ERT_NOTIFICATION_TABLE.'.url_hash','=',ERT_SCRAPE_CACHE_TABLE.'.code')
                ->whereNull(ERT_NOTIFICATION_TABLE.'.deleted_at')
                ->whereNotIn(ERT_NOTIFICATION_TABLE.'.status_code',[ERT_STATUS_GRADE,ERT_STATUS_SCHEDULER_SCRAPE])
                ->select(ERT_SCRAPE_CACHE_TABLE.'.code',ERT_INPUT_TABLE.'.id AS input_id',ERT_NOTIFICATION_TABLE.'.id AS notification_id')
                ->distinct()
                ->get();
            */

            //trace_sql();
            $scraped = Scrape_cache::join(ERT_NOTIFICATION_TABLE,ERT_NOTIFICATION_TABLE.'.url_hash','=',ERT_SCRAPE_CACHE_TABLE.'.code')
                ->join(ERT_NOTIFICATION_INPUT_TABLE, ERT_NOTIFICATION_INPUT_TABLE.'.notification_id', '=', ERT_NOTIFICATION_TABLE.'.id')
                ->join(ERT_INPUT_TABLE, ERT_INPUT_TABLE.'.id','=',ERT_NOTIFICATION_INPUT_TABLE.'.input_id')
                ->whereNotIn(ERT_INPUT_TABLE.'.status_code',[ERT_STATUS_GRADE,ERT_STATUS_SCHEDULER_SCRAPE])
                ->whereNull(ERT_INPUT_TABLE.'.deleted_at')
                ->whereNotIn(ERT_NOTIFICATION_TABLE.'.status_code',[ERT_STATUS_GRADE,ERT_STATUS_SCHEDULER_SCRAPE])
                ->whereNull(ERT_NOTIFICATION_TABLE.'.deleted_at')
                ->select(ERT_SCRAPE_CACHE_TABLE.'.code',
                    ERT_INPUT_TABLE.'.status_code AS input_status',
                    ERT_INPUT_TABLE.'.id AS input_id',
                    ERT_NOTIFICATION_TABLE.'.id AS notification_id',
                    ERT_NOTIFICATION_TABLE.'.status_code AS notification_status')
                ->get();
            $scrapecleaned = count($scraped);
            ertLog::logLine("D-schedulerCleanup; found scrape_cache to clear: count=$scrapecleaned ");

            if ($scrapecleaned > 0) {
                foreach ($scraped AS $scrape) {
                    // force delete (no undelete or audit trail)
                    ertLog::logLine("D-schedulerCleanup; clear scrape_cache from notification_id=$scrape->notification_id, input_id=$scrape->input_id ");
                    Db::table(ERT_SCRAPE_CACHE_TABLE)->where('code',$scrape->code)->delete();
                }
            }

            // ** report

            if (count($job_records) > 0 || $scrapecleaned > 0 ) {
                $params = [
                    'job_inputs' => $job_records,
                    'scrapecleaned' => $scrapecleaned,
                ];
                ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.scheduler_cleanup', $params);
            }

        } else {

            ertLog::logLine("E-schedulerCleanup; ; error cannot login as Scheduler");

        }

        SELF::endScheduler();

        return $cnt;
    }


}
