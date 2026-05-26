<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\cleanup\scartCleanup;
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

        $mode = $this->option('mode', 'proxy');
        $this->info("D-Test testUpdateWhois; mode=$mode");

        if ($mode == 'proxy') {

            $jobreports = scartUpdateWhois::checkProxyServices();
            $this->info("D-jobreports=" . print_r($jobreports,true) );

        } elseif ($mode == 'reset') {

            $alwaysmax = date('Y-m-d H:i:s',strtotime("-1 week"));
            $this->info("D-alwaysmax=$alwaysmax" );
            scartCleanup::cleanupWhoisCache('testUpdateWhois', $alwaysmax);

        }

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
            ['mode', 'm', InputOption::VALUE_OPTIONAL, 'mode', 'proxy'],
        ];
    }


}
