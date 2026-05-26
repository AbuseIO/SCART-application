<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\cleanup\scartArchive;
use abuseio\scart\classes\scheduler\scartSchedulerArchive;
use abuseio\scart\classes\scheduler\scartSchedulerCleanup;
use abuseio\scart\models\Scrape_cache;
use Illuminate\Console\Command;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\browse\scartBrowserDragon;
use abuseio\scart\classes\helpers\scartLog;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class testArchive extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testArchive';

    /**
     * @var string The console command description.
     */
    protected $description = 'testArchive';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        scartLog::setEcho(true);

        // log console options
        $this->info('testArchive');

        scartLog::logLine("D-Run scartSchedulerArchive::doJob");
        //scartArchive::setTestmode(true);
        scartSchedulerArchive::doJob();

        $this->info("testArchive; end" );

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
        ];
    }


}
