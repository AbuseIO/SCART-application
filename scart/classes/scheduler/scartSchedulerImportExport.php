<?php
namespace abuseio\scart\classes\scheduler;

use Config;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartImportMailbox;
use abuseio\scart\classes\iccam\scartImportICCAM;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\iccam\scartExportICCAM;
use abuseio\scart\classes\iccam\scartICCAMmapping;

class scartSchedulerImportExport extends scartScheduler {

    public static function doJob() {

        if (SELF::startScheduler('ImportExport', 'importExport')) {

            $scheduler_process_count = Systemconfig::get('abuseio.scart::scheduler.scheduler_process_count',15);

            // IMPORT Mailbox
            scartImportMailbox::importMailbox();

            if (scartICCAMmapping::isActive()) {

                // check if interface (temp) not in maintenance
                if (Systemconfig::get('abuseio.scart::scheduler.importexport.iccam_active', false)) {

                    scartLog::logLine("D-".SELF::$logname."; import/export interface ICCAM ON");

                    // IMPORT ICCAM
                    scartImportICCAM::doImport($scheduler_process_count);

                    // EXPORT ICCAM
                    $export_iccam_eachtime = Systemconfig::get('abuseio.scart::iccam.exportmax',20);
                    // Note: export can handle much more then import -> multiply scheduler_process_count
                    scartExportICCAM::doExport($export_iccam_eachtime);

                } else {

                    scartLog::logLine("D-".SELF::$logname."; import/export interface ICCAM OFF (maintenance?)");
                }

            }

        }

        SELF::endScheduler();

    }

}
