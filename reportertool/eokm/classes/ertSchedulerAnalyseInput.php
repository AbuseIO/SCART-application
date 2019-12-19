<?php
namespace reportertool\eokm\classes;

use Config;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_input;
use reportertool\eokm\classes\ertScheduler;

class ertSchedulerAnalyseInput extends ertScheduler {

    /**
     * Schedule AnalyzeInput
     *
     * Login scheduler account
     * Select inputs with status scheduler_scrape
     * Select notifications with status scheduler_scrape
     * Analyze and get images/whois
     * Notify scheduler receipient
     *
     * @return int
     */
    public static function doJob() {

        $cnt = 0;

        if (SELF::startScheduler('AnalyseInput','scrape')) {

            // login okay
            $job_nots = $job_inputs = array();

            /** SCHEDULED INPUT(S) **/

            $scheduler_process_count = Config::get('reportertool.eokm::scheduler.scheduler_process_count',15);

            // find all input(s) with status=scheduled
            $inputs = Input::where('status_code',ERT_STATUS_SCHEDULER_SCRAPE)
                ->take($scheduler_process_count)->get();
            //$inputs = Input::where('status_code','aaa')->get();

            if (count($inputs) > 0) {

                ertLog::logLine("D-scheduleAnalyseInput; analyzing " . count($inputs) . " input(s)...");

                foreach ($inputs AS $input) {

                    // do analyse

                    try {
                        $result = ertAnalyzeInput::doAnalyze($input);
                    } catch (\Exception $err) {
                        ertLog::logLine("E-ScheduleAnalyseInput.doAnalyze exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                        $result = [
                            'status' => false,
                            'warning' => 'error analyzing input - manual action needed',
                            'notcnt' => 0,
                        ];
                    }

                    if ($result['status']) {
                        $input->status_code = ERT_STATUS_GRADE;
                        $warning = '';
                    } else {
                        $input->status_code = ERT_STATUS_CANNOT_SCRAPE;
                        $warning = $result['warning'];
                    }
                    $input->save();
                    $input->logText("Set status_code on: " . $input->status_code);

                    ertLog::logLine("D-scheduleAnalyseInput; url=$input->url, status=$input->status_code, warning=$warning ");

                    $job_inputs[] = [
                        'filenumber' => $input->filenumber,
                        'url' => $input->url,
                        'status' => $input->status_code,
                        'notcnt' => $result['notcnt'],
                        'warning' => $warning,
                    ];

                    $cnt += 1;
                }

            } else {
                //ertLog::logLine("D-scheduleAnalyseInput; no (more) input(s) to process");
            }

            if (count($job_nots) > 0 || count($job_inputs) > 0) {

                $params = [
                    'job_inputs' => $job_inputs,
                    'job_nots' => $job_nots,
                ];
                ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.scheduler_analyze_input', $params);

            }


        } else {

            ertLog::logLine("E-Error; cannot login as Scheduler");

        }

        SELF::endScheduler();

        return $cnt;
    }



}
