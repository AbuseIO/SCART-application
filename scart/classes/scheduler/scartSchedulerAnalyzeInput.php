<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\aianalyze\scartAIanalyze;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Scrape_cache;
use Config;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Input;
use abuseio\scart\Controllers\Startpage;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\mail\scartAlerts;

class scartSchedulerAnalyzeInput extends scartScheduler {

    /**
     * Schedule AnalyzeInput
     *
     * -1-
     * Select (mainurl) inputs with status scheduler_scrape
     *   a) Analyze and get images/whois
     *   b) If to-be-classified (grade) and AI analyzer active, then call (notify) AI analyzer
     * Notify scheduler operator
     *
     * -2-
     * check if AI analyzer is active (eg AI)
     * If so, check if AI analyzing is ready -> if ready, then process result
     *
     *
     * @return int
     */
    public static function doJob() {

        $cnt = 0;

        if (SELF::startScheduler('AnalyseInput','scrape')) {

            /** SCHEDULED INPUT(S) **/

            // login okay
            $job_inputs = array();

            // load addon (if active)
            $AIaddon = (scartAIanalyze::isActive()) ? Addon::getAddonType(SCART_ADDON_TYPE_AI_IMAGE_ANALYZER) : false;

            // Each scheduler time process available records within the scheduler_process_minutes

            $scheduler_process_count = Systemconfig::get('abuseio.scart::scheduler.scrape.scheduler_process_count','');
            if ($scheduler_process_count=='') $scheduler_process_count = Systemconfig::get('abuseio.scart::scheduler.scheduler_process_count',15);

            $scheduler_process_minutes = Systemconfig::get('reportertool.eokm::scheduler.scrape.scheduler_process_minutes','');
            if ($scheduler_process_minutes=='') $scheduler_process_minutes = 5;

            // STEP-1 Check if records waiting for AI analyze results

            if ($AIaddon) {
                $job_inputs = self::checkWaitingAI($AIaddon,$scheduler_process_minutes);
            }

            // STEP-2 Scrape inputs

            $count = Input::where('status_code',SCART_STATUS_SCHEDULER_SCRAPE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)
                ->count();
            scartLog::logLine("D-scheduleAnalyseInput; process minutes=$scheduler_process_minutes; total records to analyze: $count");

            $maxmins = $scheduler_process_minutes * 60;  // get seconds
            $curtime = microtime(true);
            $endtime = $curtime + $maxmins;


            // find all input(s) with status=scheduled
            $input = Input::where('status_code',SCART_STATUS_SCHEDULER_SCRAPE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)
                ->orderBy('received_at','DESC')
                ->first();
            while ($input && ($curtime <= $endtime)) {

                $cnt += 1;
                scartLog::logLine("D-scheduleAnalyseInput [$cnt]; filenumber=$input->filenumber; seconds still to go: " . ($endtime - $curtime) );

                // set working

                // init/start browser
                scartBrowser::startBrowser();

                // log old/new for history
                $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_WORKING,"Working on analyze (scrape)");

                $input->status_code = SCART_STATUS_WORKING;
                $input->save();

                $warning = '';
                $warning_timestamp = '['.date('Y-m-d H:i:s').'] ';

                try {

                    // do analyze
                    $result = scartAnalyzeInput::doAnalyze($input);

                    if ($result['status']) {
                        if ($input->status_code == SCART_STATUS_WORKING) {

                            // next fase

                            // Note: prerelease AI; only for webform source
                            if ($input->source_code == SCART_SOURCE_CODE_WEBFORM && $AIaddon) {
                                $status_next = SCART_STATUS_SCHEDULER_AI_ANALYZE;
                            } else {
                                $status_next = SCART_STATUS_GRADE;
                            }

                            // log old/new for history
                            $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,$status_next,"Analyze (scrape) done; next fase");
                            $input->status_code = $status_next;
                            $input->classify_status_code = SCART_STATUS_GRADE;
                            $warning = '';
                        } else {
                            // status_code already set on next step
                            $warning = $result['warning'];
                        }
                    } else {

                        // log old/new for history
                        $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_CANNOT_SCRAPE,"Cannot scrape");
                        $input->status_code = SCART_STATUS_CANNOT_SCRAPE;
                        $warning = $result['warning'];
                    }
                    if ($warning) $input->logText('Cannot scrape reason: ' . $warning);
                    $input->save();

                } catch (\Exception $err) {

                    scartLog::logLine("E-ScheduleAnalyseInput.doAnalyze exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                    $warning = 'error analyzing input - manual action needed';
                    $result = [
                        'status' => false,
                        'warning' => $warning,
                        'notcnt' => 0,
                    ];

                    // log old/new for history
                    $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_CANNOT_SCRAPE,"Cannot scrape");
                    $input->status_code = SCART_STATUS_CANNOT_SCRAPE;
                    $input->save();
                }

                // log result status
                $input->logText("Set status_code on: " . $input->status_code);

                scartLog::logLine("D-scheduleAnalyseInput [$cnt]; filenumber=$input->filenumber, url=$input->url, received_at=$input->received_at, status=$input->status_code, warning=$warning ");

                // STEP-3; push records to AI analyze if AI active and input set on classify (grade)

                if ($AIaddon && $input->status_code == SCART_STATUS_SCHEDULER_AI_ANALYZE) {

                    if (self::pushRecordsAI($AIaddon,$input)) {
                        // report new status
                        $job_inputs[$input->filenumber]['status'] = SCART_STATUS_SCHEDULER_AI_ANALYZE;
                    }

                }

                // fill job report
                $job_inputs[$input->filenumber] = [
                    'filenumber' => $input->filenumber,
                    'url' => $input->url,
                    'status' => $input->status_code,
                    'notcnt' => $result['notcnt'],
                    'warning' => (($warning) ? $warning_timestamp . $warning : $warning),
                ];

                // NEXT record

                $input = Input::where('status_code',SCART_STATUS_SCHEDULER_SCRAPE)
                    ->where('url_type',SCART_URL_TYPE_MAINURL)
                    ->orderBy('received_at','DESC')
                    ->first();
                $curtime = microtime(true);

                // stop browser
                scartBrowser::stopBrowser();
            }

            // log/alert

            if (count($job_inputs) > 0) {

                $params = [
                    'job_inputs' => $job_inputs,
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_analyze_input', $params);

            } else {

                scartLog::logLine("D-scheduleAnalyseInput; no input records found");

            }

        }

        SELF::endScheduler();

        return $cnt;
    }


    static function checkWaitingAI($AIaddon,$scheduler_process_minutes) {

        /**
         * After the scrape&whois step the records are set send to the AI module and set on ANALYZE_AI status (if image data)
         *
         * In this step we poll the AI module for the results of the AI analyze
         * If we have the results of all image from a mainurl, when set mainurl on classify (grade)
         *
         */

        $job_inputs = [];

        $count = Input::where('status_code',SCART_STATUS_SCHEDULER_AI_ANALYZE)
            ->count();
        scartLog::logLine("D-scheduleAnalyseInput; process minutes=$scheduler_process_minutes; total records to check AI analyzer: $count");

        $maxmins = $scheduler_process_minutes * 60;  // get seconds
        $curtime = microtime(true);
        $endtime = $curtime + $maxmins;

        // find all input(s) with status=AI_ANALYZE
        // and with last update more then 15 minute ago -> give AI module time to process
        $beforetime = date('Y-m-d H:i:s',strtotime("-15 minutes"));
        $input = Input::where('status_code',SCART_STATUS_SCHEDULER_AI_ANALYZE)
            ->where('url_type',SCART_URL_TYPE_MAINURL)
            ->where('updated_at','<=',$beforetime)
            ->orderBy('id','ASC')
            ->first();
        while ($input && ($curtime <= $endtime)) {

            // select all records (WITHOUT mainurl) with waiting status
            $records = Input::join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.input_id', '=', SCART_INPUT_TABLE.'.id')
                ->where(SCART_INPUT_PARENT_TABLE.'.deleted_at',null)
                ->where(SCART_INPUT_PARENT_TABLE.'.parent_id', $input->id)
                ->where(SCART_INPUT_TABLE.'.id','<>',$input->id)
                ->where(SCART_INPUT_TABLE.'.url_type', '<>', SCART_URL_TYPE_VIDEOURL)
                ->where(SCART_INPUT_TABLE.'.status_code', SCART_STATUS_SCHEDULER_AI_ANALYZE)
                ->select(SCART_INPUT_TABLE.'.*')
                ->get();

            if (count($records) > 0) {

                scartLog::logLine("D-ScheduleAnalyseInput; $input->filenumber; images waiting for AI analyze: ".count($records));

                $done = true;
                foreach ($records AS $record) {
                    $done = self::pollAI($record,$AIaddon,$done);
                }

            } else {

                $done = true;

            }

            if ($done) {

                scartLog::logLine("D-ScheduleAnalyseInput; $input->filenumber; images AI analyzed ");

                $done = self::pollAI($input,$AIaddon,$done);

                if ($done) {

                    $logtimestamp = '['.date('Y-m-d H:i:s').'] ';

                    // fill job report
                    $job_inputs[$input->filenumber] = [
                        'filenumber' => $input->filenumber,
                        'url' => $input->url,
                        'status' => $input->status_code,
                        'notcnt' => $input->delivered_items,
                        'warning' =>  '',
                    ];

                }

            }

            // NEXT MAINURL record

            $input = Input::where('status_code',SCART_STATUS_SCHEDULER_AI_ANALYZE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)
                ->where('id','>',$input->id)
                ->orderBy('id','ASC')
                ->first();
            $curtime = microtime(true);

        }

        return $job_inputs;
    }

    /**
     * Push records and input itself to AI module
     *
     * @param $AIaddon
     * @param $input
     */
    public static function pushRecordsAI($AIaddon,$input) {

        // get all records and put into ai_analyze mode (if imagedata)

        $records = Input::join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.input_id', '=', SCART_INPUT_TABLE.'.id')
            ->where(SCART_INPUT_PARENT_TABLE.'.deleted_at',null)
            ->where(SCART_INPUT_PARENT_TABLE.'.parent_id', $input->id)
            ->where(SCART_INPUT_TABLE.'.status_code', SCART_STATUS_GRADE)
            ->where(SCART_INPUT_TABLE.'.url_type', '<>', SCART_URL_TYPE_VIDEOURL)
            ->select(SCART_INPUT_TABLE.'.*')
            ->get();

        // @TO-DO; (question) skip screenshots... -> indicator field in input
        // @TO-DO; (question) skip grade_code not-illegal and ignore?

        $intoAIstatus = false;
        $doneinputs = [];
        foreach ($records AS $record) {
            $doneinputs[] = $record->id;
            $intoAIstatus = self::pushAI($record,$AIaddon,$intoAIstatus);
        }

        if (!in_array($input->id,$doneinputs)) {
            // parent also
            $intoAIstatus = self::pushAI($input,$AIaddon,$intoAIstatus);
            $doneinputs[] = $input->id;
        }

        if (!$intoAIstatus) {
            // no image submitted -> set SCART_STATUS_GRADE
            scartLog::logLine("D-ScheduleAnalyseInput; no image pushed to AI analyzer ");
            // log old/new for history
            $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_GRADE,"No push to AI module done");
            $input->status_code = SCART_STATUS_GRADE;
            $input->save();
        } else {
            scartLog::logLine("D-ScheduleAnalyseInput; pushed ".count($doneinputs)." image(s) to AI analyzer ");
        }

        return $intoAIstatus;
    }

    /**
     * Push record to AI module
     *
     * @param $record
     * @param $AIaddon
     * @param $intoAIstatus
     * @return bool
     */
    static function pushAI($record,$AIaddon,$intoAIstatus) {

        $image = Scrape_cache::where('code',$record->url_hash)->first();

        if ($image) {

            $data = explode(',', $image->cached);
            $base64 = $data[1];
            $parm = [
                'action' => 'push',
                'post' =>
                    [
                        'SCART_ID' => $record->filenumber,
                        'image' => $base64,
                    ],
            ];
            if (Addon::run($AIaddon,$parm)) {

                $intoAIstatus = true;

                // log old/new for history
                $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_SCHEDULER_AI_ANALYZE,"Wait for AI analyze");

                $record->status_code = SCART_STATUS_SCHEDULER_AI_ANALYZE;
                $record->save();

                $record->logText("Pushed image to AI analyzer; filenumber=$record->filenumber");

            } else {

                // @TO-DO; may be retry 3 times?

                scartLog::logLine("W-ScheduleAnalyseInput; error adding image to AI Analyzer; filenumber=$record->filenumber; error: " . Addon::getLastError($AIaddon));
            }

        } else {

            scartLog::logLine("W-ScheduleAnalyseInput; cannot find imagedata (cache); filenumber=$record->filenumber, hash=$record->url_hash");

        }

        return $intoAIstatus;
    }

    /**
     * Poll AI module for analyze results
     * Return false if not yet done
     * Update record if done or error/timeout status
     *
     * @param $record
     * @param $AIaddon
     * @param $done
     * @return false|mixed
     */

    static function pollAI($record,$AIaddon,$done) {

        // check if we have an image
        $image = Scrape_cache::where('code',$record->url_hash)->first();

        if ($image) {

            $parm = [
                'action' => 'poll',
                'post' => $record->filenumber,
            ];
            $result = Addon::run($AIaddon,$parm);

            if ($result) {

                foreach ($result AS $name => $value) {
                    $record->addExtrafield( SCART_INPUT_EXTRAFIELD_PWCAI,$name,$value);
                }
                $logtext = "add AI analyze results (attributes); number of attributes=".count($result);
                scartLog::logLine("D-scheduleAnalyseInput; filenumber=$record->filenumber; $logtext");

                // log old/new for history
                $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_GRADE,"Got AI analyze results");

                $record->status_code = SCART_STATUS_GRADE;
                $record->save();
                $record->logText($logtext);

            } else {

                $resulterror = Addon::getLastError($AIaddon);
                if ($resulterror) {

                    $logtext = "error in AI analyze: $resulterror";
                    scartLog::logLine("E-scheduleAnalyseInput; filenumber=$record->filenumber; $logtext");

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_GRADE,"Error in AI module; skip");

                    // SKIP AI
                    $record->status_code = SCART_STATUS_GRADE;
                    $record->save();
                    $record->logText($logtext);

                } else {

                    $lasttime = strtotime($record->updated_at);

                    // skip if timeout (2022/1/21; 4 hours)
                    if (time() - $lasttime >= SCART_MAX_TIME_AI_ANALYZE) {

                        $logtext = "timeout waiting for AI module - skip  AI";
                        scartLog::logLine("E-scheduleAnalyseInput; filenumber=$record->filenumber; $logtext");

                        // log old/new for history
                        $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_GRADE,ucfirst($logtext));

                        // go to classify
                        $record->status_code = SCART_STATUS_GRADE;
                        $record->save();
                        $record->logText($logtext);

                    } else {
                        // not (yet) ready
                        $done = false;
                    }

                }

            }

        } else {

            $logtext = "No image for AI analyze";
            scartLog::logLine("W-scheduleAnalyseInput; filenumber=$record->filenumber; $logtext");

            // log old/new for history
            $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_GRADE,"No image for AI analyze; skip");

            // SKIP AI
            $record->status_code = SCART_STATUS_GRADE;
            $record->save();
            $record->logText($logtext);

        }

        return $done;
    }


}
