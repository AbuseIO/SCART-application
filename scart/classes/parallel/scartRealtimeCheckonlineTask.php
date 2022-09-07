<?php

/**
 * Realtime Checkonline Task
 *
 * standalone threat wait for own channel to receive filenumbers to process for checkonline
 *
 * bootstrap needed because of running within clean threat
 *
 * returns function
 *
 */

use parallel\{Future, Runtime, Channel};

use abuseio\scart\models\Input;
use abuseio\scart\classes\online\scartCheckOnline;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\parallel\scartRuntime;
use abuseio\scart\classes\parallel\scartTask;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\whois\scartRIPEdb;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Addon;

return function ($taskname,$basepath) {

    // bootstrap -> init else we have no (laravel app) context
    $errtxt = require_once $basepath.'/plugins/abuseio/scart/classes/parallel/scartRealtimeBootstrap.php';
    if ($errtxt) {
        scartLog::logLine("E-Bootstrap errtxt: $errtxt");
    }

    if ($taskname) {

        $taskerror = 0;
        $record = '';

        try {

            if (scartTask::startTask($taskname,'checkntd')) {

                $runtime = new scartRuntime($taskname);
                $runtime->initChannel();

                $run = true;

                while ($taskerror < 3 && $run) {

                    try {

                        $cnt = 0;
                        // start looping
                        while ($run) {

                            scartLog::logLine("D-" . scartTask::$logname . "; wait for messages on channel '$taskname'");
                            $filenumber = $runtime->readChannel();

                            scartLog::logLine("D-" . scartTask::$logname . "; received message '$filenumber'");
                            if ($filenumber) {
                                if (trim($filenumber) == 'stop') {
                                    scartLog::logLine("D-" . scartTask::$logname . "; got STOP message");
                                    $run = false;
                                } else {
                                    $record = Input::where('filenumber', $filenumber)->first();
                                    if ($record) {
                                        // check always if still valid CHECKONLINE status -> push can be quicker then processing
                                        if (in_array($record->status_code,[SCART_STATUS_SCHEDULER_CHECKONLINE,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,SCART_STATUS_FIRST_POLICE])) {
                                            scartLog::logLine("D-" . scartTask::$logname . "; doCheckIllegalOnline filenumber '$filenumber' with lastseen of '$record->lastseen_at'");
                                            $result = scartCheckOnline::doCheckIllegalOnline($record, 1, 1);
                                            if (count($result) > 0) {
                                                $params = [
                                                    'job_inputs' => $result,
                                                ];
                                                scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO, 'abuseio.scart::mail.scheduler_check_ntd', $params);
                                            }
                                        } else {
                                            scartLog::logLine("D-" . scartTask::$logname . "; filenumber '$filenumber' not valid for CHECKONLINE (anymore); status=$record->status_code ");
                                            // don't forget to reset lock set by the dispatcher
                                            scartCheckOnline::setLock($record->id,false);
                                        }

                                    } else {
                                        scartLog::logLine("W-" . scartTask::$logname . "; input with filenumber '$filenumber' not found");
                                    }
                                }
                            }

                            /**
                             * Clear caching in memory from time to time;
                             * - whois
                             * - rules
                             * - scartLog
                             * - Abusecontacts
                             * - Addon
                             * Else we blowup the memory...
                             *
                             * Count > 100 input records
                             * Can also be time (6 or 12 hours)
                             *
                             */

                            $cnt += 1;
                            if ($cnt > 100) {
                                scartLog::logLine("D-" . scartTask::$logname . "; reset caches (cnt=$cnt)");
                                scartRules::resetCache();
                                scartRIPEdb::resetCache();
                                scartWhois::resetCache();
                                Abusecontact::resetCache();
                                Addon::resetCache();
                                $cnt = 0;
                            }

                            scartLog::logMemory(scartTask::$logname);

                            if (scartLog::hasError()) {
                                scartLog::errorMail(scartTask::$logname . "; error(s) found");
                            }
                            scartLog::resetLog();

                            // reset when here
                            $taskerror = 0;
                        }


                    } catch (\Exception $err) {

                        scartLog::logLine("E-" . scartTask::$logname . "; exception '" . $err->getMessage() . "', at line " . $err->getLine());
                        scartLog::errorMail(scartTask::$logname . "; error(s) found");
                        scartLog::resetLog();

                        if ($record) {
                            // don't forget to reset lock set by the dispatcher
                            scartCheckOnline::setLock($record->id,false);
                        }
                        $taskerror += 1;

                    }

                    // error log

                }

            }

            scartTask::endTask();

        } catch (\Exception $err) {

            scartLog::logLine("E-" . scartTask::$logname . "; exception '" . $err->getMessage() . "', at line " . $err->getLine());
            $taskerror += 1;

        }

        if ($taskerror >= 3) {

            // notify operator ->
            // report admin
            $params = [
                'reportname' => "E-" . scartTask::$logname . "; STOP because of to many of repeating errors ",
                'report_lines' => "task error count: $taskerror",
            ];
            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN, 'abuseio.scart::mail.admin_report', $params);

        }

    } else {
        scartLog::logLine('W-scartRealtimeCheckonlineTask; empty taskname!?');
    }

    // return name for future->value()
    return $taskname;
};
