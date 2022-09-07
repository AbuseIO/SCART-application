<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\browse\scartCURLcalls;
use Illuminate\Console\Command;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Input;
use abuseio\scart\models\Scrape_cache;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\classes\helpers\scartLog;

class testLog extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testLog';

    /**
     * @var string The console command description.
     */
    protected $description = 'Test log';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option parameters
        $number = $this->option('number', '');
        $this->info("testLog; $number x errorLog" );

        if ($number) {
            for ($i=0;$i<$number;$i++) {
                scartLog::logLine("E-Test errorlog");
                scartLog::errorMail('TESTING LOG; number='.($i + 1));
            }
        }

        // log console work done

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
            ['number', 'nb', InputOption::VALUE_OPTIONAL, 'number', false],
        ];
    }


}
