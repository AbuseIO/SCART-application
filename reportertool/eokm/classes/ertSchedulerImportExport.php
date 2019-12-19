<?php
namespace reportertool\eokm\classes;

use Config;

use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertScheduler;

class ertSchedulerImportExport extends ertScheduler {

    public static function doJob() {

        if (SELF::startScheduler('ImportExport', 'importExport')) {

            $scheduler_process_count = Config::get('reportertool.eokm::scheduler.scheduler_process_count',15);

            // IMPORT Mailbox
            ertImportMailbox::importMailbox();

            // IMPORT ICCAM
            ertImportICCAM::doImport($scheduler_process_count);

            // EXPORT ICCAM
            ertExportICCAM::doExport();

        }

        SELF::endScheduler();

    }

}
