<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use Config;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartImportMailbox;
use abuseio\scart\classes\mail\scartAlerts;

class scartSchedulerExport extends scartScheduler {

    public static function doJob() {

        if (SELF::startScheduler('Export', 'export')) {

            // Import&export ICCAM
            if (scartICCAMinterface::isActive()) {
                // EXPORT ICCAM
                scartICCAMinterface::export();
            }

        }

        SELF::endScheduler();

    }

}
