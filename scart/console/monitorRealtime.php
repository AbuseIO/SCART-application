<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\parallel\scartRealtimeMonitor;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class monitorRealtime extends Command {

    private $release = '1.0';

    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:monitorRealtime';

    /**
     * @var string The console command description.
     */
    protected $description = 'monitor realtime processes ';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // log console options
        $this->info('Monitor Realtime - release '.$this->release);

        $headers = [
            'status',
            'count',
            'remark',
        ];

        $realtimests = scartRealtimeMonitor::realtimeStatus();

        $this->table($headers,$realtimests);

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
