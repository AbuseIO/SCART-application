<?php

namespace abuseio\scart\console;

use abuseio\scart\Models\ImportWebform;
use Config;

use Illuminate\Console\Command;
use abuseio\scart\classes\mail\scartReadMail;
use abuseio\scart\classes\mail\scartImportMailbox;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Systemconfig;

class readMailTest extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:readMailTest';

    /**
     * @var string The console command description.
     */
    protected $description = 'Read MAIL on server';

    /**
     * Execute the exconsole command.
     * @return void
     */
    public function handle() {

        scartLog::setEcho(true);

        $del = $this->option('delete');

        scartLog::logLine("D-readMailTest; delete=$del");
        if (!$del) {
            scartLog::logLine("D-readMailTest; use abuseio:readMailTest -d del");
        }

        scartReadMail::init();

        // get all
        $messages = scartReadMail::getInboxMessages(10);
        if ($messages) {

            scartLog::logLine("D-readMailTest; process " . count($messages) . ' messages');

            foreach($messages as $msg) {

                $report = "message '{$msg->getSubject()}' (uid={$msg->getId()} from '{$msg->getFrom()}' arrived at '{$msg->getDate()}', with {$msg->getBodyLinesCount()} body lines";
                scartLog::logLine("D-readImportMailbox; $report");

                if (ImportWebform::importWebformSubject($msg->getSubject())) {

                    scartLog::logDump("D-readMailTest; import webform subject!");

                    $loglines = ImportWebform::importWebformMessage($msg);

                    scartLog::logDump("D-readMailTest; loglines=",$loglines);


                } else {

                    scartLog::logDump("D-readMailTest; bodyLines=",$msg->getBodyLines());

                }

                if ($del){
                    scartLog::logLine("D-readMailTest; delete message ");
                    $msg->delete();
                }

            }

            scartReadMail::close();

        } else {
            scartLog::logLine("D-readMailTest; no message(s)");
        }

        scartLog::logLine("D-readMailTest; end");

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
            ['delete', 'd', InputOption::VALUE_OPTIONAL, 'Delete'],
        ];
    }


}
