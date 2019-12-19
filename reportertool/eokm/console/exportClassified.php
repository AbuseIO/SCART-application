<?php

namespace reportertool\eokm\console;

use reportertool\eokm\classes\ertExport;
use reportertool\eokm\classes\ertLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Grade_question;
use ReporterTool\EOKM\Models\Grade_question_option;
use ReporterTool\EOKM\Models\Grade_answer;

class exportClassified extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:exportClassified';

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
        $class = ($class == ERT_GRADE_ILLEGAL) ?  ERT_GRADE_ILLEGAL : ERT_GRADE_NOT_ILLEGAL;
        $outputfile = $this->option('outputfile');
        if (empty($outputfile)) $outputfile = $type . '_' . $class . '_output.csv';

        // log console options
        $this->info("exportClassified; version=".ertExport::$version.", type=$type, class=$class");

        if (empty($from)) $from = '2000-01-01';
        if (empty($to)) $to = '2999-01-01';

        if ($type=='classified') {
            $lines = ertExport::exportClassified($class,$from,$to);
        } elseif ($type == 'whois') {
            $lines = ertExport::exportWhois($class,$from,$to);
        }

        file_put_contents($outputfile, implode("\n", $lines) );
        $this->info("exportClassified; $type output in file '$outputfile''");

        if (ertLog::hasError()) $this->error(ertLog::returnLoglines());

        // log console work done
        $this->info("exportClassified; ". (count($lines) - 1) ." exported" );

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
            ['type', 'tp', InputOption::VALUE_OPTIONAL, 'Type (classified,whois)', 'classified'],
            ['from', 'f', InputOption::VALUE_OPTIONAL, 'Date from', ''],
            ['to', 't', InputOption::VALUE_OPTIONAL, 'Date to', ''],
            ['class', 'c', InputOption::VALUE_OPTIONAL, 'Classification (illegal or not-illegal)', ERT_GRADE_QUESTION_GROUP_ILLEGAL],
            ['outputfile', 'o', InputOption::VALUE_OPTIONAL, 'Classification', ''],
        ];
    }


}
