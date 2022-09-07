<?php

namespace abuseio\scart\console;

use Config;

use Illuminate\Console\Command;
use abuseio\scart\classes\mail\scartEXIM;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\models\Ntd_template;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Systemconfig;

class sendMailTest extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:sendMailTest';

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

        scartLog::setEcho(true);

        if ($id) {

            $status = scartEXIM::getMTAstatus($id);
            scartLog::logLine("D-getMTAstatus($id)=$status");

        } else {

            // send NTD

            $msg = Ntd_template::first();

            $fields = [
                'abuselinks' => '(urls))',
                'owner' => '(test)',
                'online_since' => date('Y-m-d H:i:s'),
            ];

            $to = $this->option('to');
            if (!$to) $to = Systemconfig::get('abuseio.scart::alerts.recipient');
            //scartLog::logLine("D-sendMailTest; send NTD to: $to ");
            $message = scartMail::sendNTD($to,$msg->subject,$msg->body);
            if ($message) {
                scartLog::logLine("D-sendMailTest; message-id: ".$message['id']);
            } else {
                scartLog::logLine("W-NTD send but got NO message_id; cannot verify ");
            }

        }

        $this->info(str_replace("<br />\n",'',scartLog::returnLoglines()));

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
