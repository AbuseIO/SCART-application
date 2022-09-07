<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use abuseio\scart\classes\scheduler\scartScheduler;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\classes\export\scartExport;

class exportClassified extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:exportClassified';

    /**
     * @var string The console command description.
     */
    protected $description = 'exportClassified';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $type = $this->option('type');
        $from = $this->option('from');
        $to = $this->option('to');
        $class = $this->option('class');
        $class = ($class == SCART_GRADE_ILLEGAL) ?  SCART_GRADE_ILLEGAL : SCART_GRADE_NOT_ILLEGAL;
        $outputfile = $this->option('outputfile');
        if (empty($outputfile)) $outputfile = $type . '_' . $class . '_output.csv';

        if (empty($from)) $from = '2000-01-01';
        if (empty($to)) $to = '2999-01-01';

        // convert always to midnight
        $from = date('Y-m-d 23:59:59', strtotime($from));
        $to = date('Y-m-d 00:00:00', strtotime($to));

        // log console options
        $this->info("exportClassified; version=".scartExport::$version.", type=$type, class=$class, from=$from, to=$to, outputfile=$outputfile");

        scartLog::setEcho(true);

        $memory_min =  scartScheduler::setMinMemory('8G');

        if ($type=='classified') {
            $lines = scartExport::exportClassified($class,$from,$to);
            file_put_contents($outputfile, implode("\n", $lines) );
            $this->info("exportClassified; $type output in file '$outputfile' ");
        } elseif ($type == 'whois') {
            $lines = scartExport::exportWhois($class,$from,$to);
            file_put_contents($outputfile, implode("\n", $lines) );
            $this->info("exportClassified; $type output in file '$outputfile' ");
        } elseif ($type == 'all') {
            $lines = scartExport::exportAll($from,$to,$outputfile);
            $this->info("exportClassified; $type output in file '$outputfile' ");
        } elseif ($type == 'ntd') {
            $lines = scartExport::exportNTD($from,$to);
            file_put_contents($outputfile, implode("\n", $lines) );
            $this->info("exportClassified; $type output in file '$outputfile' ");
        } elseif ($type == 'ntdclosed') {
            // export closed urls from NTD
            $lines = scartExport::exportNTDclosed($from);
            file_put_contents($outputfile, implode("\n", $lines) );
            $this->info("exportClassified; $type output in file '$outputfile' ");
        }

        //if (scartLog::hasError()) $this->error(scartLog::returnLoglines());

        // log console work done
        $this->info("exportClassified; ". (count($lines) - 1) ." exported into file  '$outputfile' " );

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
            ['type', 'tp', InputOption::VALUE_OPTIONAL, 'Type (classified,whois,all)', 'classified'],
            ['from', 'f', InputOption::VALUE_OPTIONAL, 'Date from', ''],
            ['to', 't', InputOption::VALUE_OPTIONAL, 'Date to', ''],
            ['class', 'c', InputOption::VALUE_OPTIONAL, 'Classification (illegal or not-illegal)', SCART_GRADE_QUESTION_GROUP_ILLEGAL],
            ['outputfile', 'o', InputOption::VALUE_OPTIONAL, 'Classification', ''],
        ];
    }


}
