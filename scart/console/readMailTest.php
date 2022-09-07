<?php

namespace abuseio\scart\console;

use Config;

use Illuminate\Console\Command;
use abuseio\scart\classes\mail\scartIMAPmail;
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
        $sub = $this->option('subject','');
        $msgno = $this->option('msgno','');

        scartLog::logLine("D-readMailTest:subject=$sub, msgno=$msgno delete=$del");
        scartLog::logLine("D-readMailTest:use abuseio:readMailTest -d <msgno> -s <del_subject> -m <msgno>");

        //The location of the mailbox.
        $host =  Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.host','');
        if ($host=='') {
            $this->error("IMAP config not set");
            return;
        }

        $config = [
            'host' =>  Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.host',''),
            'port' => Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.port', ''),
            'sslflag' => Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.sslflag', ''),
            'username' => Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.username', ''),
            'password' => Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.password', ''),
        ];

        scartIMAPmail::setConfig($config);

        // get all
        $messages = scartIMAPmail::imapGetInboxMessages(0);
        if ($messages) {

            scartLog::logLine("D-readMailTest; process " . count($messages) . ' messages from a total of ' . scartIMAPmail::imapLastMessageCount() );

            foreach($messages as $msg) {

                $msg->subject = (isset($msg->subject)) ? $msg->subject : '';
                $msg->body = scartIMAPmail::imapGetMessageBody($msg->msgno);
                $bodylines = count(explode("\n", $msg->body));

                $report = "msgno=$msg->msgno; uid=$msg->uid; bodylines=$bodylines; subject '$msg->subject' from '$msg->from' arrived at '".date('Y-m-d H:i:s',$msg->udate)."' ";
                scartLog::logLine("D-readMailTest; $report");

                if (($sub!='' && strpos($msg->subject, $sub ) === 0) || ($msg->msgno == $msgno) ){

                    if ($del) {
                        scartLog::logLine("D-Delete message msgno=$msg->msgno ");
                        scartIMAPmail::imapDeleteMessage($msg->msgno);
                    } else {
                        scartLog::logLine("D-Found message msgno=$msg->msgno ");
                    }
                }

            }

            scartIMAPmail::closeExpunge();

        } else {
            scartLog::logLine("D-No message(s)");
        }

        //$this->info(str_replace("<br />\n",'',scartLog::returnLoglines()));

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
            ['delete', 'd', InputOption::VALUE_NONE, 'Delete'],
            ['subject', 's', InputOption::VALUE_OPTIONAL, 'Subject', ''],
            ['msgno', 'm', InputOption::VALUE_OPTIONAL, 'Msgno', ''],
        ];
    }


}
