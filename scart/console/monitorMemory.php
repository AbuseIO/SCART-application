<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class monitorMemory extends Command {

    private $release = '1.0.1';

    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:monitorMemory';

    /**
     * @var string The console command description.
     */
    protected $description = 'monitor memory ';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // log console options
        $this->info('Monitor Memory - release '.$this->release);
        $report = scartLog::reportLogMemory();
        $this->table($report['headers'],$report['lines']);

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
