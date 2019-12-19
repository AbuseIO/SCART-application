<?php

namespace reportertool\eokm\console;

use Config;

use Illuminate\Console\Command;
use reportertool\eokm\classes\ertEXIM;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertMail;
use reportertool\eokm\classes\ertWhois;
use ReporterTool\EOKM\Models\Ntd_template;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class sendMailTest extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:sendMailTest';

    /**
     * @var string The console command description.
     */
    protected $description = 'Testen NTD MAIL on server';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $id = $this->option('id');

        if ($id) {

            $status = ertEXIM::getMTAstatus($id);
            ertLog::logLine("D-getMTAstatus($id)=$status");

        } else {

            // send NTD

            $msg = Ntd_template::first();

            $fields = [
                'abuselinks' => '(urls))',
                'owner' => '(test)',
                'online_since' => date('Y-m-d H:i:s'),
            ];

            $to = $this->option('to');
            if (!$to) $to = Config::get('reportertool.eokm::alerts.recipient');
            //ertLog::logLine("D-sendMailTest; send NTD to: $to ");
            $message = ertMail::sendNTD($to,$msg->subject,$msg->body,$fields);
            if ($message) {
                ertLog::logLine("D-sendMailTest; message-id: ".$message['id']);
            } else {
                ertLog::logLine("W-NTD send but got NO message_id; cannot verify ");
            }

        }

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
            ['id', 'id', InputOption::VALUE_OPTIONAL, 'message-id', ''],
            ['to', 'to', InputOption::VALUE_OPTIONAL, 'to email address', ''],
        ];
    }


}
