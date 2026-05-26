<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\scheduler\scartSchedulerCleanup;
use Config;
use Illuminate\Console\Command;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\models\Ntd_template;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Systemconfig;

class testCleanup extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testCleanup';

    /**
     * @var string The console command description.
     */
    protected $description = 'Test testCleanup';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        scartSchedulerCleanup::doJob();

        $this->info(str_replace("<br />\n",'',scartLog::returnLoglines()));

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
        ];
    }


}
