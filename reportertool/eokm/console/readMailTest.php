<?php

namespace reportertool\eokm\console;

use Config;

use Illuminate\Console\Command;
use reportertool\eokm\classes\ertIMAPmail;
use reportertool\eokm\classes\ertImportMailbox;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertMail;
use ReporterTool\EOKM\Models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class readMailTest extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:readMailTest';

    /**
     * @var string The console command description.
     */
    protected $description = 'Read MAIL on server';

    /**
     * Execute the exconsole command.
     * @return void
     */
    public function handle() {

        $rd = $this->option('readdelete', 'n');

        ertLog::logLine("D-readMailTest: readdelete=$rd");

        //The location of the mailbox.
        $host =  Config::get('reportertool.eokm::scheduler.importExport.readmailbox.host','');
        if ($host=='') {
            $this->error("IMAP config not set");
            return;
        }

        ertImportMailbox::readImportMailbox([
            'host' =>  Config::get('reportertool.eokm::scheduler.importExport.readmailbox.host',''),
            'port' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.port', ''),
            'sslflag' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.sslflag', ''),
            'username' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.username', ''),
            'password' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.password', ''),
        ]);

        $this->info(str_replace("<br />\n",'',ertLog::returnLoglines()));

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
            ['readdelete', 'rd', InputOption::VALUE_OPTIONAL, 'Read and delete', 'n'],
        ];
    }


}
