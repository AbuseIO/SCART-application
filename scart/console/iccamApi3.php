<?php
namespace abuseio\scart\console;

use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMcurl;
use abuseio\scart\classes\iccam\api3\classes\ScartExportICCAMV3;
use abuseio\scart\classes\iccam\api3\models\ScartICCAMapi;
use abuseio\scart\classes\iccam\api3\models\scartICCAMfieldsV3;
use abuseio\scart\classes\iccam\api3\ScartICCAM;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMAuthentication;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMContent;


use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\models\Systemconfig;
use Illuminate\Console\Command;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Input;
use Config;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\InputOption;

class ICCAMAPI3 extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:ICCAMAPI3';
    protected $description = 'Testen ICCAM API versie 3.0';
    protected $signature = 'abuseio:ICCAMAPI3
        {mode? : read: read reports from lastdate, token: show token get: read content item, get_report: read report, put_action: put action, loadiccamfields: INIT basic iccam values}
        {--l|lastdate= : read from date, default current time}
        {--c|count= : read number of records, default 20}
        {--i|id= : content id }
        {--a|action= : action id }
        ';
    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {

        $mode = $this->argument('mode');
        $lastdate = $this->option('lastdate');
        if ($lastdate=='') $lastdate = date('Y-m-d');
        $count = $this->option('count');
        if (empty($count)) $count = 20;
        $id = $this->option('id');
        $actionID = $this->option('action');
        if (empty($actionID)) $actionID = SCART_ICCAM_ACTION_NI;

        $this->info("D-ICCAMAPI3 start; mode=$mode, lastdate=$lastdate, count=$count, id=$id, actionID=$actionID");

        $valid = true;
        $result = '';
        $showhelp = true;

        scartLog::setEcho(true);

        try {

            if (in_array($mode,['read','token','get','get_report','put_action','loadiccamfields','special','export'])) {

                ICCAMcurl::setDebug(true);

                // Check if we can do (ICCAM) requests and get Token
                if (ICCAMAuthentication::login('ICCAMAPI3')) {

                    scartLog::logLine("D-ICCAMAPI3; authenticated" );

                    switch ($mode) {

                        case 'read':

                            $showhelp = false;

                            //$calls = ['getUnassessed','getUnactioned','getnoreference'];
                            $calls = ['getnoreference'];
                            foreach ($calls as $call) {
                                scartLog::logLine("D-ICCAMAPI3; $call; read count=$count" );
                                // 12 records
                                $reports = (new ScartICCAMapi())->$call($count,$lastdate);

                                if ($reports) {

                                    scartLog::logLine("D-ICCAMAPI3; got $call count=".count($reports));
                                    $reportid = '';
                                    foreach ($reports as $report) {
                                        if ($report->reportId != $reportid) {
                                            $mainreport = (new ScartICCAMapi())->getReport($report->reportId);
                                            scartLog::logDump("D-ICCAMAPI3; mainreport=",$mainreport );
                                            $reportid = $report->reportId;
                                        }

                                        $detail = (new ScartICCAMapi())->getContent($report->contentId);
                                        $scartimport = array_merge((array)$report,(array)$detail);
                                        scartLog::logLine("D-ICCAMAPI3; merged detail=" . print_r($scartimport,true) );

                                    }

                                } else {

                                    scartLog::logLine("D-ICCAMAPI3; NO $call records");

                                }

                            }
                            break;

                        case 'token':

                            $showhelp = false;
                            scartLog::logDump("D-ICCAMAPI3; token=",ICCAMAuthentication::getToken());

                            break;

                        case 'get':

                            $showhelp = false;
                            $content = (new ScartICCAMapi())->getContent($id);
                            scartLog::logDump("D-ICCAMAPI3; content=",$content);

                            break;

                        case 'get_report':

                            $showhelp = false;
                            $reportdata = (new ScartICCAMapi())->getReport($id);
                            if (!empty($reportdata)) {
                                foreach ($reportdata->reportContents as $key => $content) {
                                    $contentdata = (new ScartICCAMapi())->getContent($content->contentId);
                                    $reportdata->reportContents[$key] = (object)array_merge((array)$content,(array)$contentdata);
                                }
                            }
                            scartLog::logDump("D-ICCAMAPI3; report=",$reportdata);

                            break;

                        case 'put_action':

                            $showhelp = false;
                            $reason = 'Other';

                            $iccamActionId = scartICCAMfieldsV3::getActionID($actionID);
                            $actionname = scartICCAMfieldsV3::getActionName($actionID);
                            $iccamdate = scartICCAMfieldsV3::iccamDate(time());
                            // Note: reason has to be in sync with ICCAM reason (text)
                            $iccamActionReasonId = scartICCAMfieldsV3::getActionReasonID($reason);
                            $action = (object) [
                                'actionType' => $iccamActionId,
                                'actioningAnalystName' => Systemconfig::get('abuseio.scart::iccam.apiuser', ''),
                                'actionDate' => $iccamdate,
                            ];
                            if ($iccamActionId > 3) {
                                $action->reasonId = $iccamActionReasonId;
                                $action->reasonText = $reason;
                            }
                            scartLog::logDump("D-ICCAMAPI3; actionname=$actionname, postContentAction.action=",$action);

                            ICCAMcurl::setDebug(true);
                            $result = (new ScartICCAMapi())->postContentAction($id, $action);
                            scartLog::logDump("D-ICCAMAPI3; postContentAction.result=",$result);

                            //$content = (new ScartICCAMapi())->getContent($id);
                            //scartLog::logDump("D-ICCAMAPI3; getContent=",$content);

                            break;

                        case 'loadiccamfields':

                            $showhelp = false;
                            if ($this->confirm('You are sure to clear & reload the iccam api table')) {
                                scartLog::setEcho(true);
                                $this->onICCAMrefresh();
                            }
                            break;

                        case 'special':

                            // SPECIAL DEDICATED TEST

                            scartLog::logLine("D-ICCAMAPI3; do special ICCAM action");

                            $newIpAddress = '45.156.25.234';
                            $newCountryCode = 'RU';
                            $contentId = '7832959';

                            $iccamexportv3 = new ScartExportICCAMV3();
                            $iccamexportv3->postMovedAction($contentId,$newIpAddress,$newCountryCode);

                            $showhelp = false;
                            break;


                        case 'export':

                            if (scartICCAMinterface::isActive()) {
                                // EXPORT ICCAM
                                scartICCAMinterface::export();
                            }

                            break;

                    }

                } else {
                    scartLog::logLine("D-ICCAMAPI3; not authenticated" );
                }

            } else {
                scartLog::logLine("D-ICCAMAPI3; unknown mode '$mode'" );
            }

        } catch (\Exception $err) {

            scartLog::logLine("E-ICCAMAPI3; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

        if ($showhelp) {
            Artisan::call('abuseio:ICCAMAPI3 -h');
            $this->info(Artisan::output());
        }

        $this->info('D-End');
    }

    public function onICCAMrefresh()
    {

        /*
        * Iccam_entities
        * - age-categories
        * - gender-categories
        * - payment-methods
        * - site-types
        * - commercialities
        * - classifications
        */


        if (ICCAMAuthentication::login('onICCAMrefresh')) {

            $iccamapi2fields = [

                'getSitesTypes' => ['sourceUrlSiteType','SiteTypeID'],
                'getCommercialities' => ['sourceUrlCommerciality','CommercialityID'],
                'getPaymentMethods' => ['sourceUrlPaymentMethods','PaymentMethodID'],
                'getActionsTypes' => ['action','actionID'],

                'getClassifications' => ['classification','ClassificationID'],
                'getAgeCategories' => ['ageCategorization','AgeGroupID'],
                'getGenderCategories' => ['genderCategorization','GenderID'],

                'getVirtual' => ['virtualContentCategorization','IsVirtual',[
                    0 => 'No',
                    1 => 'Yes',
                ]],
                'getChildModeling' => ['childModelingCategorization','IsChildModeling',[
                    0 => 'No',
                    1 => 'Yes',
                ]],
                'getUserGC' => ['userGeneratedContentCategorization','IsUserGC',[
                    0 => 'No',
                    1 => 'Yes',
                ]],

            ];

            foreach ($iccamapi2fields as $iccamapi => $fieldconfig) {

                $this->delField($fieldconfig[0],$fieldconfig[1]);

                if (isset($fieldconfig[2])) {
                    foreach ($fieldconfig[2] as $iccamid => $iccamname) {
                        $this->addField($fieldconfig[0],$fieldconfig[1],$iccamid,$iccamname);
                    }
                } else {

                    $iccamvalues = (new ScartICCAMapi())->$iccamapi();

                    //scartLog::logDump("D-onICCAMrefresh; call $iccamapi; values=",$iccamvalues);
                    foreach ($iccamvalues as $iccamvalue) {
                        // Note: action has type, no id
                        $id = (isset($iccamvalue->id) ? $iccamvalue->id : $iccamvalue->type);
                        $this->addField($fieldconfig[0],$fieldconfig[1],$id,$iccamvalue->name);
                    }

                }

            }

        }

    }

    private function delField($iccamfield,$internfield) {

        \abuseio\scart\models\Iccam_api_field::where('iccam_field',$iccamfield)
            ->where('scart_field',$internfield)
            ->delete();
    }

    private function addField($iccamfield,$internfield,$iccamid,$iccamname) {

        scartLog::logLine("D-addField($iccamfield,$internfield,$iccamid,$iccamname)");

        $iccamapifield = new \abuseio\scart\models\Iccam_api_field();
        $iccamapifield->iccam_field = $iccamfield;
        // Note: default scart_code = id
        $iccamapifield->iccam_id = $iccamapifield->scart_code = $iccamid;
        $iccamapifield->iccam_name = $iccamname;
        $iccamapifield->scart_field = $internfield;
        $iccamapifield->save();

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
            ['mode', 'm', InputOption::VALUE_OPTIONAL, '','read'],
            ['lastdate', 'l', InputOption::VALUE_OPTIONAL, 'lastdate', ''],
            ['count', 'c', InputOption::VALUE_OPTIONAL, 'count', ''],
            ['id', 'i', InputOption::VALUE_OPTIONAL, 'id', ''],
        ];
    }

}
