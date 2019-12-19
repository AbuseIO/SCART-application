<?php

namespace reportertool\eokm\console;

use Illuminate\Console\Command;
use League\Flysystem\Exception;
use reportertool\eokm\classes\ertICCAM;
use reportertool\eokm\classes\ertICCAM2ERT;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertUsers;
use ReporterTool\EOKM\Models\Input;
use Config;
use ReporterTool\EOKM\Models\Notification;
use Symfony\Component\Console\Input\InputOption;

class iccamApi extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:iccamApi';

    /**
     * @var string The console command description.
     */
    protected $description = 'Testen ICCAM API';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {

        $mode = $this->option('mode','read_open');
        $reportID = $this->option('reportID', '');
        $actionID = $this->option('actionID', '');
        $website = $this->option('website', '');

        $this->info("D-iccamapi start; mode=$mode, reportID=$reportID, actionID=$actionID");

        $valid = true;
        $result = '';

        if (ertICCAM::login()) {

            $this->info("D-Login success");

            //$config =  Config::get('database', '');
            //$this->info(print_r($config, true));

            try {

                switch ($mode) {

                    case 'read':
                        $result = ertICCAM::readICCAM($reportID);
                        ertLog::logLine("D-iccamapi read result=" . print_r($result,true) );
                        break;

                    case 'read_open':
                        $result = $this->readOpen();
                        ertLog::logLine("D-iccamapi; read_open result=" . print_r($result,true) );
                        break;

                    case 'read_from':
                        $result = $this->readFrom($reportID);
                        ertLog::logLine("D-iccamapi; read_from ($reportID) result=" . print_r($result,true) );
                        break;

                    case 'read_updates':
                        $result = $this->readUpdates($reportID);
                        ertLog::logLine("D-iccamapi; readUpdates result=" . print_r($result,true) );
                        break;

                    case 'read_actions':
                        $result = ertICCAM::readActionsICCAM($reportID);
                        ertLog::logLine("D-iccamapi readActionsICCAM from $reportID; result=" . print_r($result,true) );
                        break;

                    case 'insert':
                        $result = $this->insertReportID($website);
                        //ertLog::logLine("D-iccamapi insertReportID; result=" . print_r($result,true) );
                        break;

                    case 'insert_action':
                        $result = $this->insertAction($reportID,$actionID);
                        ertLog::logLine("D-iccamapi insertAction; result=" . print_r($result,true) );
                        break;

                    default:
                        ertLog::logLine("D-Unknown mode=$mode");
                        break;
                }

            } catch (Exception $err) {

                ertLog::logLine("E-iccamApi exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }


            $this->info(ertLog::returnLoglines());

            ertICCAM::close();
        }

        $this->info('D-End');
    }

    public function readOpen() {

        //$result = ertICCAM::read('GetOpenReports');
        $result = ertICCAM::read('GetReports');
        // test get last reportID
        $ret = [];
        $ret[] = $result[0];
        //$ret[] = $result[count($result) - 1];
        return $ret;
    }

    public function readFrom($reportID) {

        /**
         * reportStatus
         * 0 Open
         * 1 Closed
         * 2 Either
         *
         * reportOrigin
         * 0 Reported by user’s hotline
         * 1 Hosted in user’s country
         * 2 Either
         *
         */

        $result = ertICCAM2ERT::readICCAMfrom([
            'startID' => $reportID,
            'status' => ERT_ICCAM_REPORTSTATUS_EITHER,
            'origin' => ERT_ICCAM_REPORTORIGIN_USERCOUNTRY,
        ]);
        return $result;
    }

    public function readUpdates($reportID) {

        $result = ertICCAM::read('GetReportUpdates?id='.$reportID);
        return $result;
    }

    public function insertReportID($website) {

        if ($website) {

            // get one for copy material
            $not = Notification::where('grade_code',ERT_GRADE_ILLEGAL)
                ->first();
            if ($not) {
                $not->url = $website;
                $not->note = 'Insert from commandline';
            }

        } else {

            $not = Notification::where('grade_code',ERT_GRADE_ILLEGAL)
                ->where('status_code','<>',ERT_STATUS_CLOSE)
                ->where('reference','')
                ->first();

        }

        if (!$not) {
            $this->info('D-No more illegal records');
            return 0;
        }

        $this->info("D-Got #filenumer $not->filenumber, grade=$not->grade_code, status=$not->status_code");

        return ertICCAM2ERT::insertERT2ICCAM($not);
    }

    public function insertAction($reportID,$actionID) {

        $workuser_id = ertUsers::getWorkuserId('dagmar@meldpunt-kinderporno.nl');

        $country = 'NL';
        $reason = 'Insert by iccamAPI';

        return ertICCAM2ERT::insertERTaction2ICCAM($reportID,$actionID,$workuser_id,$country,$reason);
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
            ['mode', 'm', InputOption::VALUE_OPTIONAL, 'Mode: read, read_open, read_action, insert, insert_action','read_open'],
            ['reportID', 'r', InputOption::VALUE_OPTIONAL, 'reportID', ''],
            ['actionID', 'a', InputOption::VALUE_OPTIONAL, 'actionID', ''],
            ['website', 'w', InputOption::VALUE_OPTIONAL, 'website', ''],
        ];
    }





    //******** TESTING CALLS & TRY-OUT *******

    function dummy() {


        /*
        //$result = ertICCAM::read('GetReports/865923');
        //$result = ertICCAM::read('GetReports?startDate=2019-01-01&endData=2019-05-01');
        if ($result) {

            $outfile = 'iccamapi_json.txt';
            file_put_contents($outfile, print_r($result, true) );
            ertLog::logLine("D-iccamapi output read in '$outfile'");

        }
        */

        //$reportID = '865922';
        //$reportID = '865997';
        //$result = ertICCAM::read('GetReports/'.$reportID);
        //ertLog::logLine("D-iccamapi ReportID=$reportID; first item: " . print_r($result,true) );

        /*
        if ($result) {
            $outfile = 'iccamapi_json.txt';
            file_put_contents($outfile, print_r($result, true) );
            ertLog::logLine("D-iccamapi output read in '$outfile'");
        }
        */
        //ertLog::logLine("D-iccamapi ReportID=865922; BEFORE memo: " . $result->Memo );
        //ertLog::logLine("D-iccamapi ReportID=865922; result: " . print_r($result,true) );

        /*
        //unset($result->Items);
        $result->ObjectID = $result->ReportID;
        unset($result->ReportID);
        $result->Memo .= "\n" . 'Add test on ' . date('Y-m-d H:i:s');
        //ertLog::logLine("D-iccamapi ReportID=865923; result: " . print_r($result,true) );
        */

        $hotlineID = 43;  // EOKM

        // fields: Memo, ReportID
        $reportinsert = [
            "HotlineID" => $hotlineID,
            'Analyst' => 'dagmar@meldpunt-kinderporno.nl',
            "Url" => "https://bit-".date('YmdHms').".nl",
            "HostingCountry" => "NL",
            "HostingIP" => "185.46.65.12",
            "HostingNetName" => "Network in Arnhem",
            "Received" => "2019-10-22T08:15:00Z",
            "ReportingHotlineReference" => "M00000123",
            "HostingHotlineReference" => "A00003456",
            "WebsiteName" => null,
            "PageTitle" => null,

            // not in ERT
            //"ReportStatus" => 1,        // open
            //"ReportOrigin" => 0,        // reported by user's hotline

            'Memo' => 'Add test memo on ' . date('Y-m-d H:i:s'),
            "Username" => null,
            "Password" => null,

            "CommercialityID" => 1,
            "ContentType" => 0,
            "SiteTypeID" => 2,
            "PaymentMethodID" => null,

            "ClassifiedBy" => "dagmar@meldpunt-kinderporno.nl",
            "Country" => "US",
            "GenderID" => 1,
            "EthnicityID" => 1,
            "AgeGroupID" => 1,
            "ClassificationDate" =>  "2019-10-22T10:18:00Z",
            "IsVirtual" => false,
            "IsChildModeling" => false,
            "IsUserGC" => false,
        ];

        //$result = ertICCAM::update('SubmitLegacyReport', $reportinsert);
        //ertLog::logLine("D-iccamapi; SubmitLegacyReport result: " . print_r($result,true) );
        //$reportID = $result;

//$this->info(ertLog::returnLoglines());
//ertICCAM::close();
//return;

        /*
        $result = ertICCAM::read('GetReports/'.$reportID);
        ertLog::logLine("D-iccamapi read INSERTED ReportID=$reportID; result: " . print_r($result,true) );
        $itemID = $result->Items[0]->ID;
        */
        /*
        $itemupdate = [
            "ObjectID" => $reportID,
            "HotlineID" => $hotlineID,
            "Url" => "https://wordpressveilig.nl/subdir/item1",

            "HostingCountry" => "US",
            "HostingIP" => "93.184.216.34",
            "HostingNetName" => "Dummy Networks",
            "Received" => "2019-10-10T08:15:00Z",
            "ReportingHotlineReference" => null,
            "HostingHotlineReference" => null,
            "WebsiteName" => null,
            "PageTitle" => null,
            "SiteTypeID" => null,

            "Memo" => null,
            "Username" => null,
            "Password" => null,
            "ContentType" => 0,
            "ClassifiedBy" => "dagmar@meldpunt-kinderporno.nl",

            "Country" => "US",
            "GenderID" => 1,
            "EthnicityID" => 1,
            "AgeGroupID" => 1,
            "ClassificationDate" =>  "2015-07-09T06:18:00Z",
            "IsVirtual" => false,
            "IsChildModeling" => false,
            "IsUserGC" => false,
        ];

        $result = ertICCAM::update('SubmitItemUpdate', $itemupdate);
        ertLog::logLine("D-iccamapi; SubmitItemUpdate result: " . print_r($result,true) );
        */

        /**
         * ID Action
         * 1 Report to LEA   -> policie
         * 2 Report to ISP   -> host / registrar / site owner
         * 3 Content removed
         * 4 Site down
         * 5 Moved
         * 6 Not Legally Accessible [deprecated] [when used the action is converted to ‘Not Illegal’]
         * 7 Not Illegal
         *
         */

        $reportID = 866035;

        $result = ertICCAM::read('GetReports?id='.$reportID);
        ertLog::logLine("D-iccamapi read GetReports; reportid=$reportID; result: " . print_r($result,true) );
        //$itemID = (int) $result->Items[0]->ID;

        //$result = ertICCAM::read('GetReports?id='.$reportID);
        //ertLog::logLine("D-iccamapi read reportID=$reportID; result: " . print_r($result,true) );

        // ActionID: 1+2;  mag na aanmaken
        // ActionID: 3+4; daarna mag niets meer
        // 1: LEA
        // 2: ISP
        // 3: CR  Content Removed
        // 4: CU  Content Unavaiable
        // 5: MO  Moved


        // fields: Memo, ReportID
        $resultupdate = [
            'ObjectID' => $reportID,
            'ActionID' => 4,
            'Analyst' => "dagmar@meldpunt-kinderporno.nl",
            'Date' => "2019-10-14T08:15:00Z",
            'Country' => 'NL',
            'Reason' => "Illegal content",
        ];

        ertLog::logLine("D-iccamapi ReportID=$reportID; SubmitReportUpdate json: " . print_r($resultupdate,true) );
        $result = ertICCAM::update('SubmitReportUpdate', $resultupdate);

        $result = ertICCAM::read('GetReportUpdates?id='.$reportID);
        ertLog::logLine("D-iccamapi GetReportUpdates reportID=$reportID; result: " . print_r($result,true) );

        //$result = ertICCAM::read('GetReports?id='.$reportID);
        //ertLog::logLine("D-iccamapi read reportID=$reportID; result: " . print_r($result,true) );

        $this->info(ertLog::returnLoglines());
        ertICCAM::close();
        return;

        /**
         * vr.2019.07.05
         *
         * -1-
         * onderstaande werkt op basis van een ACTIONID; dit is enkel aanvullende info in ICCAM
         * het werkt NIET om ICCAM velden aan te passen, zoals memo of whatever
         *
         * -2-
         * het werkt op basis van losse updates
         * je kunt een nieuwe opvoeren met SubmitLegacyReport
         * je kunt items toevoegen met SubmitItemUpdate
         * je kunt status zetten van een bestaande via SubmitReportUpdate
         *
         * 2019/10/Gs: oude interface/functie naam (ref supportdesk ICCAM):
         * - de functie van SubmitIHRMSReportUpdate is nog onbekend
         *
         */

        // fields: Memo, ReportID
        $resultupdate = [
            'ActionID' => 3,
            'Analyst' => $result->Analyst,
            'Date' => null,
            'ObjectID' => $result->ReportID,
            'Country' => 'NL',
            //'ReportID' => 865922,
            //'WebsiteName' => 'Website TEST ' . date('Y-m-d H:i:s'),
            'Memo' => $result->Memo . "\n" . 'Add test on ' . date('Y-m-d H:i:s'),
            'Reason' => 'Add reason on ' . date('Y-m-d H:i:s'),
        ];

        //ertLog::logLine("D-iccamapi ReportID=865922; SubmitReportUpdate json: " . print_r($resultupdate,true) );
        ertICCAM::update('SubmitReportUpdate', $resultupdate);

        $result = ertICCAM::read('GetReports/865922');
        ertLog::logLine("D-iccamapi ReportID=865922; AFTER memo: " . $result->Memo );
        //sertLog::logLine("D-iccamapi ReportID=865923; AFTER WebsiteName: " . $result->WebsiteName );

        $result = ertICCAM::read('GetReportUpdates?id=865922');
        ertLog::logLine("D-iccamapi ReportID=865922; GetReportUpdates: " . print_r($result,true) );

        $this->info(ertLog::returnLoglines());

    }

}
