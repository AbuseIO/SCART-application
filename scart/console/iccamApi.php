<?php
namespace abuseio\scart\console;

use abuseio\scart\classes\iccam\api2\scartICCAM;
use abuseio\scart\classes\iccam\api2\scartICCAMmapping;
use abuseio\scart\classes\iccam\api2\scartICCAMfields;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Systemconfig;
use Illuminate\Console\Command;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Input;
use Config;
use Symfony\Component\Console\Input\InputOption;

class iccamApi extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:iccamApi';

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
        $filespec = $this->option('filespec', '');
        $stageDate = $this->option('stagedate', date('Y-m-d'));
        if ($stageDate) $stageDate = strtotime($stageDate);
        $reportStage = $this->option('reportStage', '');

        $this->info("D-iccamapi start; mode=$mode, reportID=$reportID, actionID=$actionID, stageDate=$stageDate, reportStage=$reportStage");

        $valid = true;
        $result = '';

        if (scartICCAM::login()) {

            $this->info("D-Login success");

            try {

                switch ($mode) {

                    case 'read':
                        $result = scartICCAM::readICCAM($reportID);
                        scartLog::logLine("D-iccamapi read result=" . print_r($result,true) );
                        break;

                    case 'read_open':
                        $result = $this->readOpen();
                        scartLog::logLine("D-iccamapi; read_open result=" . print_r($result,true) );
                        break;

                    case 'read_stage':

                        $result = $this->readStageDate($reportStage,$stageDate);

                        if ($result) {
                            $reportids = [];
                            foreach ($result AS $report) {
                                $reportids[] = $report->ReportID . ' (Received='. $report->Received . ')';
                            }
                            scartLog::logLine("D-iccamapi; read_stage result=" . print_r($reportids,true) );
                        }
                        break;

                    case 'read_from':
                        $result = $this->readFrom($reportID);
                        scartLog::logLine("D-iccamapi; read_from ($reportID) result=" . print_r($result,true) );
                        break;

                    case 'read_updates':
                        $result = $this->readUpdates($reportID);
                        scartLog::logLine("D-iccamapi; readUpdates result=" . print_r($result,true) );
                        break;

                    case 'read_actions':
                        $result = scartICCAM::readActionsICCAM($reportID);
                        scartLog::logLine("D-iccamapi readActionsICCAM from $reportID; result=" . print_r($result,true) );
                        break;

                    case 'insert':
                        $result = $this->insertReportID($website);
                        //scartLog::logLine("D-iccamapi insertReportID; result=" . print_r($result,true) );
                        break;

                    case 'insert_action':
                        $result = $this->insertAction($reportID,$actionID);
                        scartLog::logLine("D-iccamapi insertAction; result=" . print_r($result,true) );
                        break;

                    case 'update':
                        $reference = scartICCAMinterface::setICCAMreportID($reportID);
                        $record = Input::where('reference',$reference)->first();
                        if ($record) {
                            scartLog::logLine("D-iccamapi insertUpdateICCAM with reportID=$reportID ");
                            $ICCAMreportID = scartICCAMmapping::insertUpdateICCAM($record);
                            scartLog::logLine("D-iccamapi insertUpdateICCAM=$ICCAMreportID");
                        }
                        break;

                    case 'file_actions':
                        if ($filespec) {

                            $content = file_get_contents($filespec);
                            if ($getactions = json_decode($content,true)) {

                                foreach ($getactions AS $getaction => $getdata) {

                                    if ($getdata['mode']=='read') {

                                        $geturl = '';
                                        foreach ($getdata['post'] AS $var => $val) {
                                            if ($geturl!='') $geturl .= '&';
                                            $geturl .= "$var=".urldecode($val);
                                        }
                                        $this->info("Read getaction: ".$getaction.'?'.$geturl);
                                        $result = scartICCAM::read($getaction.'?'.$geturl);
                                        $this->info('_result='.print_r($result,true));

                                    } elseif ($getdata['mode']=='update') {

                                        $this->info("Update getaction: ".$getaction);
                                        $result = scartICCAM::update($getaction,$getdata['post']);
                                        $this->info('_result='.print_r($result,true));

                                    } else {

                                        // not yet

                                    }

                                }

                            } else {

                                $this->warn('Filespec json error: ' . json_last_error_msg() );

                            }

                        } else {
                            $this->warn('Filespec parameter is missing');
                        }


                        break;

                    default:
                        scartLog::logLine("D-Unknown mode=$mode");
                        break;
                }

            } catch (\Exception $err) {

                scartLog::logLine("E-iccamApi exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }


            scartICCAM::close();

        }

        $this->info(scartLog::returnLoglines());

        $this->info('D-End');
    }

    public function readOpen() {

        //$result = scartICCAM::read('GetOpenReports');
        $result = scartICCAM::read('GetReports');
        // test get last reportID
        $ret = [];
        $ret[] = $result[0];
        //$ret[] = $result[count($result) - 1];
        return $ret;
    }

    public function readStageDate($reportStage,$stageDate) {

        /**
         * reportstage=1; Classification
         * reportstage=2; Monitoring
         * reportstage=3; Completed
         *
         */

        $enddate = date('Y-m-d H:i:s', strtotime('+1 hour', $stageDate));
        $startDate = ($stageDate) ? date('Y-m-d H:i:s',$stageDate) : '';

        $result = scartICCAMmapping::readICCAMfromStage([
            'stage' => $reportStage,
            'startDate' => $startDate,
            'endDate' => $enddate,
        ]);
        return $result;
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

        $result = scartICCAMmapping::readICCAMfrom([
            'startID' => $reportID,
            'status' => SCART_ICCAM_REPORTSTATUS_EITHER,
            'origin' => SCART_ICCAM_REPORTORIGIN_USERCOUNTRY,
        ]);
        return $result;
    }

    public function readUpdates($reportID) {

        $result = scartICCAM::read('GetReportUpdates?id='.$reportID);
        return $result;
    }

    public function insertReportID($website) {

        if ($website) {

            // get one for copy material
            $not = Input::where('grade_code',SCART_GRADE_ILLEGAL)
                ->first();
            if ($not) {
                $not->url = $website;
                $not->note = 'Insert from commandline';
            }

        } else {

            $not = Input::where('grade_code',SCART_GRADE_ILLEGAL)
                ->where('status_code','<>',SCART_STATUS_CLOSE)
                ->where('reference','')
                ->first();

        }

        if (!$not) {
            $this->info('D-No more illegal records');
            return 0;
        }

        $this->info("D-Got #filenumer $not->filenumber, grade=$not->grade_code, status=$not->status_code");

        return scartICCAMmapping::insertUpdateICCAM($not);
    }

    public function insertAction($reportID,$actionID) {

        $record = new \stdClass();
        $record->workuser_id = scartUsers::getWorkuserId('dagmar@meldpunt-kinderporno.nl');

        $country = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
        $reason = 'Insert by iccamAPI';

        return scartICCAMmapping::insertERTaction2ICCAM($reportID,$actionID,$record,$country,$reason);
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
            ['filespec', 'f', InputOption::VALUE_OPTIONAL, 'filespec', ''],

            ['stagedate', 's', InputOption::VALUE_OPTIONAL, 'stagedate', ''],
            ['reportStage', 'rs', InputOption::VALUE_OPTIONAL, 'reportStage', ''],
        ];
    }

}
