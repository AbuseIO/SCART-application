<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use abuseio\scart\classes\scheduler\scartScheduler;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\classes\export\scartExport;

class exportBulk extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:exportBulk';

    /**
     * @var string The console command description.
     */
    protected $description = 'exportBulk';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $from = $this->option('from');
        $outdir = $this->option('outdir');

        // imitate export Offlimits

        if ($from && strtotime($from)) {

            scartLog::setEcho(true);

            $memory_min =  scartScheduler::setMinMemory('8G');

            $current = date('Y-m-d',strtotime($from));
            $enddate = date('Y-m-d');

            scartLog::logLine("D-exportBulk; run exports from '$from' till '$enddate' into (docker container) directory '{$outdir}'");

            while ($current < $enddate) {

                $next2date = date('Y-m-d',strtotime("$current +2 days"));

                $cmd = "abuseio:exportClassified --type=whois --class=illegal --from='{$current}' --to='{$next2date}' --outputfile={$outdir}eokm-report-{$current}.csv";
                Artisan::call($cmd);
                $this->info(Artisan::output());

//                $nextdate = date('Y-m-d',strtotime("$current +1 days"));
//                $prev2days = date('Y-m-d',strtotime("$current -2 days"));
//                $cmd = "abuseio:exportClassified --type=ntd --class=illegal --from='{$prev2days}' --to='{$nextdate}' --outputfile={$outdir}eokm-report-sent-ntd-{$current}.csv";
//                Artisan::call($cmd);
//                $this->info(Artisan::output());
//
//                $cmd = "abuseio:exportClassified --type=ntdclosed --class=illegal --to='{$current}' --outputfile={$outdir}eokm-report-closed-2pm-url-{$current}.csv";
//                Artisan::call($cmd);
//                $this->info(Artisan::output());

                $current = date('Y-m-d',strtotime("$current +1 day"));
            }


        }


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
    protected function getOptions() {
        return [
            ['from', 'f', InputOption::VALUE_OPTIONAL, 'Date from', ''],
            ['outdir', 'o', InputOption::VALUE_OPTIONAL, 'output directory', ''],
        ];
    }


}
