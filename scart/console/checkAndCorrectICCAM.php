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

class checkAndCorrectICCAM extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:checkAndCorrectICCAM';

    /**
     * @var string The console command description.
     */
    protected $description = 'checkAndCorrectICCAM';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $debug = false;

        $from = $this->option('from', '');
        $this->info("D-Start of checkAndCorrectICCAM; from=$from" . (($debug)?'  (DEBUG ON)':'  (DEBUG OFF)'));

        $this->info("D-checkAndCorrectICCAM; check & correct ICCAM hotline reference");

        if ($from && ICCAMAuthentication::login('checkAndCorrectICCAM')) {

            while ($from < date('Y-m-d')) {

                $iccamreports = (new ScartICCAMapi())->getnoreference(1000,$from);

                if ($iccamreports) {

                    $cnt = 1;
                    $max = count($iccamreports);
                    $this->info("D-checkAndCorrectICCAM; from=$from; check $max import records");
                    foreach ($iccamreports as $iccamreport) {

                        $contentItem = (new ScartICCAMapi())->getContent($iccamreport->contentId);

                        if (isset($contentItem->reportId)) {

                            $input = scartICCAMinterface::findICCAMreport($contentItem->reportId,$contentItem->contentId);
                            if ($input) {

                                $scartreference = $input->filenumber.'_'.date('YmdHi');
                                $this->info("D-checkAndCorrectICCAM; [$cnt/$max] reportId=$contentItem->reportId, contentId=$contentItem->contentId; set ICCAM hotline reference on $scartreference");
                                $result = (new ScartICCAMapi())->putContentHotlineReference($contentItem->contentId, $scartreference);

                            }

                        }

                        $cnt+=1;
                        if ($cnt % 50 == 0) $this->info("D-checkAndCorrectICCAM; [$cnt/$max]");

                    }

                } else {
                    $this->info("D-checkAndCorrectICCAM; no imports found from=$from");
                }

                $from = date('Y-m-d',strtotime($from." +1 days"));
            }



        }



    exit();

        $this->info("D-checkAndCorrectICCAM; check & insert CU action ");

        // get all the ICCAM input records with grade=SCART_GRADE_NOT_ILLEGAL and reason=NOT_FOUND and hoster=0

        if ($from) {
            $notillegals = Input::where('grade_code',SCART_GRADE_NOT_ILLEGAL)
                ->where('received_at','>=',$from)
                ->where('reference','LIKE','%#%')
                ->whereNull('host_abusecontact_id')
                ->get();
        } else {
            $notillegals = Input::where('grade_code',SCART_GRADE_NOT_ILLEGAL)
                ->where('reference','LIKE','%#%')
                ->whereNull('host_abusecontact_id')
                ->get();
        }

        $iccamCUactionId = scartICCAMfieldsV3::getActionID(SCART_ICCAM_ACTION_CU);

        $this->info("D-Total records to check=".count($notillegals));

        if (ICCAMAuthentication::login('checkAndCorrectICCAM')) {

            //ICCAMcurl::setDebug(true);

            $cnt = 1;
            foreach ($notillegals as $notillegal) {

                if (Grade_question::isNotIllegalNotFound($notillegal->id)) {

                    $this->info("D-[$cnt/id=$notillegal->id] has reason=NOT-FOUND");

                    // get (new API) contentId -> support only V3 API records
                    $contentId = scartICCAMinterface::getICCAMcontentID($notillegal->reference);

                    if ($contentId) {

                        if (!$this->hasICCAMaction($contentId,$iccamCUactionId)) {

                            $this->info("D-[$cnt/id=$notillegal->id] add ICCAM action=SCART_ICCAM_ACTION_CU");

                            if (!$debug) {

                                scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                    'record_type' => class_basename($notillegal),
                                    'record_id' => $notillegal->id,
                                    'object_id' => $notillegal->reference,
                                    'action_id' => SCART_ICCAM_ACTION_CU,
                                    'country' => '',                                // hotline default
                                    'reason' => 'SCART not found',
                                ]);

                            }

                        } else {
                            $this->info("D-[$cnt/id=$notillegal->id] has already ICCAM action=SCART_ICCAM_ACTION_CU" );
                        }

                    } else {
                        $this->info("W-[$cnt/id=$notillegal->id] has NOT ICCAM contentId set; reference=$notillegal->reference" );
                    }

                } else {
                    $this->info("W-[$cnt/id=$notillegal->id] Other or no not-illegal reason(s) found" );
                }

                $cnt += 1;
                if ($debug && $cnt > 5) break;

            }


        }



        $this->info("D-Done" );
    }


    private $reason = 0;

    function getQuestionNotIllegalReasonAnswer($input_id) {

        $answer = '';
        if ($this->reason == 0) {
            $reason = Grade_question::where('questiongroup',SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL)
                ->where('url_type',SCART_URL_TYPE_MAINURL)
                ->where('name','reason')
                ->first();
            if ($reason) {
                $this->reason = $reason->id;
            } else {
                $this->info("E-CANNOT FIND NOT-ILLEGAL REASON QUESTION!?");
            }
        }
        if ($this->reason) {
            $answer = Grade_answer::where('record_type','input')
                ->where('record_id',$input_id)
                ->where('grade_question_id',$this->reason)
                ->first();
            $answer =  ($answer) ? unserialize($answer->answer) : '';
        }
        return $answer;
    }

    private function hasICCAMaction($contentId,$iccamActionId) {

        $hasaction = false;
        $contentItem = (new ScartICCAMapi())->getContent($contentId);
        if (!empty($contentItem->actions)) {
            foreach ($contentItem->actions as $action) {
                if ($action->actionType == $iccamActionId) {
                    $hasaction = true;
                    break;
                }
            }
        }
        return $hasaction;
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
            ['from', 'f', InputOption::VALUE_OPTIONAL, 'from', ''],
        ];
    }


}
