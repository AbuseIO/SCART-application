<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\classes\mail\scartImportMailbox;
use Illuminate\Console\Command;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class importExport extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:importExport';

    /**
     * @var string The console command description.
     */
    protected $description = 'Run import & export';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // log console options
        $this->info('importExport START');

        scartLog::setEcho(true);

        if (scartImportMailbox::isActive()) {
            scartImportMailbox::importMailbox();
        } else {
            scartLog::logLine("D-".SELF::$logname."; read mailbox not setup");
        }

        // Import&export ICCAM
        if (scartICCAMinterface::isActive()) {
            // IMPORT ICCAM
            scartICCAMinterface::import();
            // EXPORT ICCAM
            scartICCAMinterface::export();
        }

        // log console work done
        $this->info("importExport DONE" );

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
