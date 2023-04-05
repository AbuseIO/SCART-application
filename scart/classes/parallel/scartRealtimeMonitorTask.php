<?php

/**
 * Realtime monitor Task
 *
 * standalone threat wait with own channel to receive monitor stats
 * synchronize concurrent updates of monitors stats with channel
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
use abuseio\scart\classes\parallel\scartRealtimeCheckonline;
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

        try {

            if (scartTask::startTask($taskname,'monitor')) {

                $runtime = new scartRuntime($taskname);
                $runtime->initChannel();

                $run = true;

                while ($taskerror < 3 && $run) {

                    try {

                        $cnt = 0;

                        // start looping
                        while ($run) {

                            scartLog::logLine("D-" . scartTask::$logname . "; reading messages on channel '$taskname'");
                            $stats = $runtime->readChannel();

                            if (isset($stats['sender']) && isset($stats['record_id']) && isset($stats['set']) && isset($stats['taskname'])) {
                                scartLog::logLine("D-" . scartTask::$logname . "; received message: ".implode(',',$stats));
                                scartRealtimeCheckonline::setNamedLock($stats['record_id'],$stats['set'],$stats['taskname']);
                            } else {
                                scartLog::logLine("E-" . scartTask::$logname . "; unknown format message received: ".print_r($stats,true));
                            }

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

                        $taskerror += 1;

                    }

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
        scartLog::logLine('W-scartRealtimeMonitorTask; empty taskname!?');
    }

    // return name for future->value()
    return $taskname;
};
