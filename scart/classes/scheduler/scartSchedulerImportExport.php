<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use Config;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartImportMailbox;
use abuseio\scart\classes\mail\scartAlerts;

class scartSchedulerImportExport extends scartScheduler {

    public static function doJob() {

        if (SELF::startScheduler('ImportExport', 'importExport')) {

            // IMPORT Mailbox
            if (scartImportMailbox::isActive()) {
                scartImportMailbox::importMailbox();
            } else {
                scartLog::logLine("D-".SELF::$logname."; read mailbox not setup");
            }

            // Import&export ICCAM
            if (scartICCAMinterface::isActive()) {

                // IMPORT ICCAM
                scartICCAMinterface::import();
                // EXPORT ICCAM
                scartICCAMinterface::export();

            }

        }

        SELF::endScheduler();

    }

}
