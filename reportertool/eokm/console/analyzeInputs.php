<?php

namespace reportertool\eokm\console;

use Illuminate\Console\Command;
use reportertool\eokm\classes\ertAnalyzeInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class analyzeInputs extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:analyzeInputs';

    /**
     * @var string The console command description.
     */
    protected $description = 'Analyse (open) input record(s)';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // log console options
        $this->info('AnalyzeInputs');

        // do the job
        $cnt = ertAnalyzeInput::scheduleAnalyseInput();

        // log console work done
        $this->info("AnalyzeInputs; $cnt processed" );

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
