<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\User_options;
use Config;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

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

        if ($set) {
            // dynamic waiting for running job(s)
            $timeout = 60;
            while ($timeout > 0) {
                $count = User_options::where('user_id', 0)->where('name','like','scheduler%')->where('value',serialize(1))->count();
                if ($count == 0) break;
                sleep(1);
                $timeout -= 1;
            }
            if ($timeout > 0) {
                $this->info("D-setMaintenance; no active jobs (anymore)");
            } else {
                $this->info("D-setMaintenance; still running jobs (count=$count)");
            }
        }

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
