<?php
namespace abuseio\scart\console;

/**
 * OFFLIMITS Netherlands - oktober 2023
 *
 * The old interface and also the new (current) is not effecient enough for all the cases
 *
 * This tool is used to correct (sync) ICCAM and/or SCART statuses
 *
 */

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMAuthentication;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMcurl;
use abuseio\scart\classes\iccam\api3\classes\ScartExportICCAMV3;
use abuseio\scart\classes\iccam\api3\models\ScartICCAMapi;
use abuseio\scart\classes\iccam\api3\models\scartICCAMfieldsV3;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Input_parent;
use Illuminate\Console\Command;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class imageTypes extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:imageTypes';

    /**
     * @var string The console command description.
     */
    protected $description = 'imageTypes';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $input = $this->option('input', '');

        scartLog::setEcho(true);

        if ($input) {
            scartLog::logLine("D-Start of imageTypes; input=$input");

            $data = file_get_contents($input);
            $lines = explode("\n",$data);

            foreach ($lines as $line) {

                $record = Input::where('filenumber',trim($line))->first();
                if ($record) {

                    $mimeType = mime_content_type($record->url);
                    scartLog::logLine("D-url=$record->url, mimeType=$mimeType");

                } else {
                    scartLog::logLine("W-Cannot find filenumber=$record->filenumber");
                }

            }


        } else {
            scartLog::logLine("W-No input file");
        }

        scartLog::logLine("D-Done" );
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
            ['input', 'i', InputOption::VALUE_OPTIONAL, 'input', ''],
        ];
    }


}
