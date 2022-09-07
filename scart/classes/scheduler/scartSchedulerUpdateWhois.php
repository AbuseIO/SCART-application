<?php
namespace abuseio\scart\classes\scheduler;

use Db;
use Config;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\whois\scartUpdateWhois;

class scartSchedulerUpdateWhois extends scartScheduler {

    /**
     * Schedule update whois
     *
     *
     */
    public static function doJob() {

        $cnt = 0;

        if (SELF::startScheduler('updatewhois','updatewhois')) {

            Try {

                $job_records = scartUpdateWhois::checkProxyServices();

                // ** report
                if (count($job_records) > 0 ) {
                    $params = [
                        'job_records' => $job_records,
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_update_whois', $params);

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
