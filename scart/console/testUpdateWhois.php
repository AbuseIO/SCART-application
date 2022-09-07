<?php

namespace abuseio\scart\console;

use Illuminate\Console\Command;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\scheduler\scartScheduler;
use abuseio\scart\classes\whois\scartUpdateWhois;
use abuseio\scart\models\Input;
use abuseio\scart\models\Log;
use abuseio\scart\classes\base\scartModel;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Db;
use Config;

class testUpdateWhois extends Command {

    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testUpdateWhois';

    /**
     * @var string The console command description.
     */
    protected $description = 'test testUpdateWhois';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // log console options

        $this->info("Test testUpdateWhois");

        $jobreports = scartUpdateWhois::checkProxyServices();

        $this->info("jobreports=" . print_r($jobreports,true) );

        /*

        $delete = $this->option('delete', '');
        $delete = ($delete);

        $this->info("Test function; delete-mod=$delete");

        scartScheduler::setMinMemory('8G');

        $max = 350000;

        $this->info("D-RAW SELECT (max=$max)" );
        $orphans = Db::select("SELECT id FROM ".SCART_INPUT_TABLE." WHERE url_type <> 'mainurl'
         AND ".SCART_INPUT_TABLE.".deleted_at IS NULL
         AND NOT EXISTS (SELECT 1 FROM ".SCART_INPUT_PARENT_TABLE." WHERE ".SCART_INPUT_PARENT_TABLE.".deleted_at IS NULL
			AND ".SCART_INPUT_PARENT_TABLE.".input_id=".SCART_INPUT_TABLE.".id)
		 LIMIT 0,$max ");
        $orphanscnt = count($orphans);
        $this->info("D-RAW SELECT; orphanscnt=$orphanscnt");

        if ($orphanscnt > 0 && $delete) {

            $this->info(date('Y-m-d H:i:s') . ": remove orphans...");

            $startTime = microtime(true);

            // turn off
            $mod = new scartModel();
            $mod->setAudittrail(false);

            $cnt = 0;
            foreach ($orphans AS $orphan) {
                $rec = Input::find($orphan->id);
                if ($rec) {
                    //$this->info("D-Orphan remove; id=$rec->id, url_type=$rec->url_type, status=$rec->status_code, grade=$rec->grade_code, url=$rec->url, ");
                    $rec->delete();
                    $cnt += 1;

                    if ($cnt % 1000 == 0) {
                        $time_end = microtime(true);
                        $execution_time = round(($time_end - $startTime), 1);
                        $endtime = round($orphanscnt * ($execution_time / $cnt) / 3600, 1);
                        $this->info(date('Y-m-d H:i:s') . ": removed=$cnt ; execution_time=$execution_time secs, endtime=$endtime hours");
                    }
                }
            }

            $time_end = microtime(true);
            $execution_time = round(($time_end - $startTime), 1);
            $this->info(date('Y-m-d H:i:s') . ": orphanscnt=$orphanscnt, removed=$cnt ; execution_time=$execution_time secs");


        } else {

            $this->info("No remove from orphans");

        }

        /*
        $params = [
            'reportname' => 'Orphans cleanup',
            'report_lines' => [
                "Found orphanscnt=$orphanscnt"
            ]
        ];
        scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
        */

        /*
        scartLog::logLine("D-INPUT SELECT");
        $orphans = Input::where('url_type','<>',SCART_URL_TYPE_MAINURL)
            ->whereNotExists(function ($query) {
                $query->select(Db::raw(1))
                    ->from(SCART_INPUT_PARENT_TABLE)
                    ->whereNull(SCART_INPUT_PARENT_TABLE.'.deleted_at')
                    ->whereRaw(SCART_INPUT_PARENT_TABLE.'.input_id = '.SCART_INPUT_TABLE.'.id');
            })
            ->get();
        $orphanscnt = count($orphans);
        scartLog::logLine("D-INPUT SELECT; orphanscnt=$orphanscnt");
        */

        // log console work done
        $this->info(scartLog::returnLoglines() );

    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['delete', 'd', InputOption::VALUE_OPTIONAL, 'delete mode', 0],
            ['wait', 'w', InputOption::VALUE_OPTIONAL, 'wait', 1],
        ];
    }


}
