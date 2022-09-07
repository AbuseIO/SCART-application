<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class checkOnline extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:checkOnline';

    /**
     * @var string The console command description.
     */
    protected $description = 'Check if (still) online';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $repeat = $this->option('repeat', '1');
        if ($repeat=='') $repeat = 1;

        // log console options
        $this->info('CheckOnline; repeat=' . $repeat );

        $cnt = 0;
        for ($i=0;$i<$repeat;$i++) {
            // do the job
            $cnt += scartAnalyzeInput::scheduleCheckOnline();
        }

        $this->info(scartLog::returnLoglines());

        // log console work done
        $this->info("CheckOnline; $cnt processed" );

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
            ['repeat', 'r', InputOption::VALUE_OPTIONAL, 'repeat count calling check_online', false],
        ];
    }


}
