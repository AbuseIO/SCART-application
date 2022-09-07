<?php

namespace abuseio\scart\console;

use Illuminate\Console\Command;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\Controllers\Startpage;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class dashboardReset extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:dashboardReset';

    /**
     * @var string The console command description.
     */
    protected $description = 'Reset dashboard cache';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // do the job
        $startPage = new Startpage();
        $startPage->resetLoadCache();

        $this->info('Dashboard cache reset done');

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
