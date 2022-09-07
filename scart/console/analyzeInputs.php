<?php

namespace abuseio\scart\console;

use Illuminate\Console\Command;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class analyzeInputs extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:analyzeInputs';

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

        $inputs = Input::where('status_code',SCART_STATUS_SCHEDULER_SCRAPE)
            ->take(1)
            ->get();

        if (count($inputs) > 0) {

            foreach ($inputs AS $input) {
                // do the job
                $report = scartAnalyzeInput::doAnalyze($input);
            }

            $this->info("Report=" . print_r($report , true) );

        }

        // log console work done
        $this->info("AnalyzeInputs done" );

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
