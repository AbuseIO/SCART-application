<?php

namespace abuseio\scart\console;

use Config;

use Illuminate\Console\Command;
use abuseio\scart\classes\mail\scartEXIM;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\models\Ntd_template;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Systemconfig;

class setMaintenance extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:setMaintenance';

    /**
     * @var string The console command description.
     */
    protected $description = 'set SCART maintenance mode on/off';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $onoff = $this->option('onoff');
        $set = ($onoff)?'1':'0';
        $this->info("D-setMaintenance; set maintenance on '$set'");
        scartLog::logLine("D-setMaintenance; console set maintenance on '$set'");
        Systemconfig::set('abuseio.scart::maintenance.mode',$set);

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
            ['onoff', 'o', InputOption::VALUE_OPTIONAL, 'onoff', ''],
        ];
    }


}
