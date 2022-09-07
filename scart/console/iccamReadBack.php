<?php
namespace abuseio\scart\console;

/**
 * Temporary job for reading ICCAM repors back in time (2020)
 *
 * Run in seperated cronjob:
 * - /usr/bin/docker exec octobercms php artisan abuseio:iccamReadBack
 *
 *
 */

use Illuminate\Console\Command;
use League\Flysystem\Exception;
use abuseio\scart\classes\helpers\scartExportICCAM;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\iccam\scartICCAM;
use abuseio\scart\classes\iccam\scartICCAMmapping;
use abuseio\scart\classes\iccam\scartImportICCAM;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Input;
use Config;
use Symfony\Component\Console\Input\InputOption;

class iccamReadBack extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:iccamReadBack';

    /**
     * @var string The console command description.
     */
    protected $description = 'ICCAM read backwards reports';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {

        /**
         * Begin bij het laatste uur van 2020 en lees elke keer een uur
         *
         * Doe dit X keer met:
         *  X = 24 = 1 dag
         *  X = 168 = 1 week
         *  X = 336 = 2 weken
         *  x = 672 = 4 weken
         *
         */

        $reports = [];
        $minutes = 60;
        $x = 672;

        scartLog::logLine("D-iccamReadBack; start");

        for ($i=0;$i < $x;$i++) {

            if ( !($lastdate = scartImportICCAM::getImportlast(SCART_INTERFACE_ICCAM_ACTION_IMPORTBACKDATE)) ) {
                $lastdate = '2020-12-31 23:00:00';
                scartImportICCAM::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTBACKDATE,$lastdate);
            }

            $this->info("iccamReadBack; read backwards from '$lastdate' ");

            $reports = array_merge($reports,scartImportICCAM::importFromLastdate($lastdate,$minutes));

            /**
             * NOTE
             *
             * Summertime 29-03-2020...
             * When stepping back from 2020-03-29 03:00:00 then we get ..2020-03-29 03:00:00
             * because 2020-03-29 02:00:00 = 2020-03-29 03:00:00
             *
             * be aware!
             *
             */

            // next call hour before
            $lastdate = date('Y-m-d H:i:00', strtotime('-'.$minutes.' min', strtotime($lastdate)));
            scartLog::logLine("D-iccamReadBack; set lastDate on next hour: $lastdate");
            scartImportICCAM::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTBACKDATE, $lastdate);

            if ($lastdate < '2020-01-01 00:00:00' ) {

                $params = [
                    'reportname' => 'ICCAM READ BACK; job 2020 done',
                    'report_lines' => [
                        "lastdate=$lastdate",
                    ]
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

            }

        }

        if (count($reports) > 0) {

            // report JOB
            $params = [
                'reports' => $reports,
            ];
            scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO, 'abuseio.scart::mail.scheduler_import_iccam', $params);

        }

        scartLog::logLine("D-iccamReadBack; end (x=$x)");
        $this->info('D-iccamReadBack; end');
    }


    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments() {
        return [
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions() {
        return [
            ['inputfile', 'i', InputOption::VALUE_OPTIONAL, 'Inputfile', ''],
            ['mode', 'm', InputOption::VALUE_OPTIONAL, 'mode', ''],
        ];
    }

}
