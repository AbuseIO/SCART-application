<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\mail\scartAlerts;
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

class testMailview extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testMailview';

    /**
     * @var string The console command description.
     */
    protected $description = 'Test testMailview';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $params = [
            'reportname' => 'TEST MAILVIEW ',
            'report_lines' => [
                "Regel-1= TEST"
            ]
        ];
        scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN, 'abuseio.scart::mail.admin_report', $params );




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
