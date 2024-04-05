<?php

namespace abuseio\scart\console;

use Config;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\models\Systemconfig;
use Symfony\Component\Console\Input\InputOption;

class scartRealtimeCheckonline extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:scartRealtimeCheckonline';

    /**
     * @var string The console command description.
     */
    protected $description = 'scartRealtimeCheckonline';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $testmode = $this->option('testmode');
        $testmode = ($testmode!='');

        $this->info("scartRealtimeCheckonline start; testmode=$testmode");
        scartLog::setEcho(true);
        $settings = [
            'test_mode' => $testmode,
        ];
        if ($testmode)  {
            // testmode -> quicker spinning down
            $settings['min_diff_spindown'] = 5;
        }

        // go running
        \abuseio\scart\classes\parallel\scartRealtimeCheckonline::run($settings);

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
            ['testmode', 't', InputOption::VALUE_OPTIONAL, 'testmode', false],
        ];
    }


}
