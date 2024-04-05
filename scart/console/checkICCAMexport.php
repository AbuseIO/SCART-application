<?php
namespace abuseio\scart\console;

/**
 * OFFLIMITS recover ICCAM API errors
 *
 * Specifieke reparatie script voor OFFLIMITS december 2023
 * Bij het toevoegen/wijzigen van een report werd de assignment
 *
 *
 *
 */

use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMAuthentication;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMcurl;
use abuseio\scart\classes\iccam\api3\models\ScartICCAMapi;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\ImportExport_job;
use abuseio\scart\models\Input_parent;
use Db;
use abuseio\scart\models\Log;
use Config;
use abuseio\scart\classes\online\scartHASHcheck;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use abuseio\scart\models\Input;
use abuseio\scart\models\Input_history;

class checkICCAMexport extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:checkICCAMexport';

    /**
     * @var string The console command description.
     */
    protected $description = 'Check if exported to ICCAM';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        scartLog::setEcho(true);

        $offset = $showcnt = $cnt = 0;

        $localCountry = 'NL';
        $moveToCountry = 'JP';

        //$filelast = 'not_assigned.csv';
        $filelast = 'iccam-move-to-JP.csv';
        $filedone = 'iccam-move-to-JP-done.csv';

        if (file_exists($filelast)) {

            if (ICCAMAuthentication::login('checkICCAMexport')) {

                $contents = file_get_contents($filelast);

                $contentIds = explode("\n",$contents);
                $tot = count($contentIds);
                foreach ($contentIds as $contentId) {

                    $contentId = trim(str_replace("\r",'',$contentId));

                    $iccamcontent = (new ScartICCAMapi())->getContent($contentId);

                    if ($iccamcontent) {

                        if ($iccamcontent->assignedCountryCode == $localCountry && $iccamcontent->url->hostingCountryCode == $localCountry) {

                            $status = 'success';
                            $result = (new ScartICCAMapi())->putContentNewHosting($contentId, $iccamcontent->url->hostingIpAddress, $moveToCountry);
                            if (ICCAMcurl::hasErrors()) {
                                scartLog::logLine("W-checkICCAMexport [$tot/$cnt]; putContentNewHosting; contentId=$contentId; moveToCountry=$moveToCountry; ICCAM error: ".ICCAMcurl::getErrors());
                                $status = 'error putContentNewHosting: '.ICCAMcurl::getErrors();
                            } else {
                                $result = (new ScartICCAMapi())->putContentAssignedCountry($contentId, $moveToCountry);
                                if (ICCAMcurl::hasErrors()) {
                                    scartLog::logLine("W-checkICCAMexport [$tot/$cnt]; putContentAssignedCountry; contentId=$contentId; moveToCountry=$moveToCountry; ICCAM error: ".ICCAMcurl::getErrors());
                                    $status = 'error putContentAssignedCountry: '.ICCAMcurl::getErrors();
                                } else {
                                    scartLog::logLine("D-checkICCAMexport [$tot/$cnt]; contentId=$contentId; set new hosting/assign countryCode=$moveToCountry ");
                                }
                            }
                            $cnt += 1;

                        } else {
                            scartLog::logLine("D-checkICCAMexport [$tot/$cnt]; contentId=$contentId; hostingCountryCode=$moveToCountry, assignedCountryCode=; do nothing");
                            $status = 'already assigned/moved to '.$moveToCountry;
                        }

                        file_put_contents($filedone,"$contentId;$status\n",FILE_APPEND);

                    } else {
                        scartLog::logLine("D-checkICCAMexport [$tot/$cnt]; can NOT find content for contentId=$contentId!?");
                    }

                    //if ($cnt > 10) break;

                }

            }

        } else {

            scartLog::logLine("W-Cannot find file '$filelast'");

        }

        scartLog::logLine("D-$cnt re-assigned");

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
            ['file', 'f', InputOption::VALUE_OPTIONAL, 'file', ''],
        ];
    }


}
