<?php
namespace abuseio\scart\classes\scheduler;

use Config;

use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_template;
use abuseio\scart\models\Ntd_url;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartSendNTD;
use abuseio\scart\classes\mail\scartAlerts;

class scartSchedulerSendNTD extends scartScheduler {

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

            $alt_email  = Systemconfig::get('abuseio.scart::scheduler.sendntd.alt_email','');
            if ($alt_email) scartLog::logLine('D-' . SELF::$logname . "; ALT_EMAIL=$alt_email (TEST MODE)");

            // not use $scheduler_process_count -> process ALL NTD's
            //$scheduler_process_count = Systemconfig::get('abuseio.scart::scheduler.scheduler_process_count',15);

            // login okay

            $cnt = 0;
            $ntd_nots = array();

            try {

                $result = scartSendNTD::waitingNTD();
                if (count($result) > 0) {
                    $ntd_nots = array_merge($ntd_nots, $result);
                }

            }  catch (\Exception $err) {

                scartLog::logLine("E-SchedulerSendNTD.waitingNTD exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

            try {

                $result = scartSendNTD::checkEXIM();
                if (count($result) > 0) {
                    $ntd_nots = array_merge($ntd_nots, $result);
                }

            }  catch (\Exception $err) {

                scartLog::logLine("E-SchedulerSendNTD.checkEXIM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

            // messages?
            if (count($ntd_nots) > 0) {

                $params = [
                    'ntd_nots' => $ntd_nots,
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_ntd_send', $params);

            }

        }

        SELF::endScheduler();

        return $cnt;
    }


}
