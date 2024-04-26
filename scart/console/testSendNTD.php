<?php

namespace abuseio\scart\console;

use Config;
use Illuminate\Console\Command;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\models\Ntd_template;
use October\Rain\Parse\Bracket;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Systemconfig;

class testSendNTD extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testSendNTD';

    /**
     * @var string The console command description.
     */
    protected $description = 'Test send NTD';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $lang = 'en';
        $abuseemail = 'gerald@svsnet.nl';

        $msg = Ntd_template::where('id','>',0)->first();
        //scartLog::logDump("D-msg.body=",$msg->body);

        $csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
        $csvtemp .= 'ntdbody-onlyurl.tpl';

        $lines = [
            [
                'url' => 'https://www.domain.nl/image1.jpg',
                'reason' => 'Not done',
            ],
            [
                'url' => 'https://www.domain.nl/image2.jpg',
                'reason' => 'Not done 2',
            ],
        ];

        $abuselinks = Bracket::parse(file_get_contents($csvtemp),['lines' => $lines]);
        //scartLog::logDump("D-abuseLinks=",$abuselinks);

        $msg_body = str_replace('<p>{{'.'abuselinks'.'}}</p>', $abuselinks, $msg->body);

        $message = scartMail::sendNTD($abuseemail,$msg->subject,$msg_body);

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
