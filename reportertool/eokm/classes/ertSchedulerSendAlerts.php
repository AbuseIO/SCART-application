<?php
namespace reportertool\eokm\classes;

use Config;

use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertScheduler;

class ertSchedulerSendAlerts extends ertScheduler {

    public static function doJob() {

        if (SELF::startScheduler('sendAlerts', 'sendalerts')) {

            try {

                $send_alerts_info  = Config::get('reportertool.eokm::scheduler.sendalerts.send_alerts_info', 15);
                if (ertAlerts::timeForSend(ERT_ALERT_LEVEL_INFO, $send_alerts_info) ) {
                    ertAlerts::sendAlerts(ERT_ALERT_LEVEL_INFO);
                }

                $send_alerts_warning  = Config::get('reportertool.eokm::scheduler.sendalerts.send_alerts_warning', 1);
                if (ertAlerts::timeForSend(ERT_ALERT_LEVEL_WARNING, $send_alerts_warning) ) {
                    ertAlerts::sendAlerts(ERT_ALERT_LEVEL_WARNING);
                }

            }  catch (\Exception $err) {

                ertLog::logLine("E-SchedulerSendAlerts; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

        }

        SELF::endScheduler();

    }

}
