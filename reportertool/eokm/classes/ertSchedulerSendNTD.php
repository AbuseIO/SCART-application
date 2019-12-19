<?php
namespace reportertool\eokm\classes;

use Config;

use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Ntd;
use ReporterTool\EOKM\Models\Ntd_template;
use ReporterTool\EOKM\Models\Ntd_url;
use reportertool\eokm\classes\ertGrade;
use reportertool\eokm\classes\ertScheduler;

class ertSchedulerSendNTD extends ertScheduler {

    /**
     * Schedule scheduleNTDsend
     *
     * check NTD status
     * if send trigger (directly or hours) then
     *   set NTD status on queued
     *   split NTD
     *   send NTD
     * check queued NTDs
     *   message-id status
     *   if found then set success or failed
     *
     * CHeck if
     *
     * Warn if for Abusecontact NO ABUSE EMAIL ADDRESS is set
     *
     */
    public static function doJob() {

        $cnt = 0;

        if (SELF::startScheduler('SendNTD','sendntd')) {

            $alt_email  = Config::get('reportertool.eokm::scheduler.sendntd.alt_email','');
            if ($alt_email) ertLog::logLine('D-' . SELF::$logname . "; ALT_EMAIL=$alt_email (TEST MODE)");
            $scheduler_process_count = Config::get('reportertool.eokm::scheduler.scheduler_process_count',15);

            // login okay

            $cnt = 0;
            $ntd_nots = array();

            try {

                $result = ertSendNTD::waitingNTD($scheduler_process_count);
                if (count($result) > 0) {
                    $ntd_nots = array_merge($ntd_nots, $result);
                }

            }  catch (\Exception $err) {

                ertLog::logLine("E-SchedulerSendNTD.waitingNTD exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

            try {

                $result = ertSendNTD::checkEXIM();
                if (count($result) > 0) {
                    $ntd_nots = array_merge($ntd_nots, $result);
                }

            }  catch (\Exception $err) {

                ertLog::logLine("E-SchedulerSendNTD.checkEXIM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

            // messages?
            if (count($ntd_nots) > 0) {

                $params = [
                    'ntd_nots' => $ntd_nots,
                ];
                ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.scheduler_ntd_failed_send', $params);

            }

        } else {

            ertLog::logLine("E-Error; cannot login as Scheduler");

        }

        SELF::endScheduler();

        return $cnt;
    }


}
