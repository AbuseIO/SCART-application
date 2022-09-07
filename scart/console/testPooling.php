<?php

namespace abuseio\scart\console;

use Config;

use parallel\{Future, Runtime};

use Illuminate\Console\Command;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\models\Systemconfig;

class testPooling extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testPooling';

    /**
     * @var string The console command description.
     */
    protected $description = 'Test Pooling';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        scartLog::setEcho(true);

        scartLog::logLine('D-Test Pooling');

        $task = static function (int $i, int $sec) {

            echo shell_exec("php artisan abuseio:GetWhois -d 127.0.0.1");

        };

        // creating a few threads
        $runtimeList = [];
        for ($i = 0; $i < 3; $i++) {
            $runtimeList[] = new Runtime();
        }

        // run all threads
        $futureList = [];
        foreach ($runtimeList as $i => $runtime) {
            echo "[run$i]";
            $futureList[] = $runtime->run($task, [$i, 3]);
        }

        // waiting until all threads are done
        // if you delete code bellow then your script will hang
        do {
            usleep(1);
            $allDone = array_reduce(
                $futureList,
                function (bool $c, Future $future): bool {
                    return $c && $future->done();
                },
                true
            );
        } while (false === $allDone);

        scartLog::logLine('D-End');

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
