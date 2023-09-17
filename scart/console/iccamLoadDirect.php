<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\iccam\api2\scartIccam;
use abuseio\scart\classes\iccam\api2\scartICCAMmapping;
use abuseio\scart\classes\iccam\api2\scartICCAMfields;
use abuseio\scart\classes\iccam\api2\scartExportICCAM;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Systemconfig;
use Illuminate\Console\Command;
use League\Flysystem\Exception;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Input;
use Config;
use Symfony\Component\Console\Input\InputOption;

class iccamLoadDirect extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:iccamLoadDirect';

    /**
     * @var string The console command description.
     */
    protected $description = 'ICCAM load direct ';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {

        scartLog::setEcho(true);

        $inputfile = $this->option('inputfile');
        if (!$inputfile) {
            scartLog::logLine("iccamLoadDirect-v2.1; read inputfile and set ICCAM action - input line format; ICCAM-ID;SCART-ID;mode[;extra]");
            return;
        }

        if (!file_exists($inputfile)) {
            scartLog::logLine("E-iccamLoadDirect; cannot find inputfile '$inputfile'");
            return;
        }

        $actions = [
            'CR' => SCART_ICCAM_ACTION_CR,  // Content Removed
            'CU' => SCART_ICCAM_ACTION_CU,  // Content Unavailable (CU)
            'NI' => SCART_ICCAM_ACTION_NI,  // Not Illegal
            'MO' => SCART_ICCAM_ACTION_MO,  // moved
        ];
        $reasons = [
            'CR' => 'SCART check (Eko) - close_offline',
            'CU' => 'SCART check (Eko) - close',
            'NI' => 'SCART check (Eko) - not_illegal',
            'MO' => 'SCART check (Eko) - moved outside NL',
        ];
        $workuserID = 0;
        $country = Systemconfig::get('abuseio.scart::classify.hotline_country', '');

        $inputlines = explode("\n", file_get_contents($inputfile));
        scartLog::logLine("iccamLoadDirect; inputfile=$inputfile; lines count=".count($inputlines));

        $cnt = 0;
        foreach ($inputlines AS $inputline) {

            // note: skip first line (header)

            if (!empty($inputline) && $cnt > 0) {

                $arr = explode(';',$inputline);

                if (count($arr) > 2) {

                    $ICCAMreportID = $arr[0];
                    $SCARTID = $arr[1];
                    $action = strtoupper($arr[2]);
                    $extra = (isset($arr[3])) ? $arr[3] : '';

                    if (isset($actions[$action])) {

                        if ($SCARTID) {
                            $input = Input::where('filenumber',$SCARTID)->first();
                        } else {
                            // inputfiles without SCARTID are also accepted
                            $input = new \stdClass();
                            $input->reference = $ICCAMreportID;
                        }

                        if ($input) {

                            if (scartICCAMinterface::getICCAMreportID($input->reference) == $ICCAMreportID) {

                                /**
                                 * Note
                                 * Direct push to ICCAM without SCART record checks
                                 *
                                 */

                                if ($action == 'MO') {
                                    // moved TO country
                                    $country = $extra;
                                } else {
                                    $country = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
                                }

                                if ($result = $this->insertERTaction2ICCAM($ICCAMreportID, $actions[$action], $country, $reasons[$action])) {
                                    scartLog::logLine("E-[row=$cnt][reportid=$ICCAMreportID]; insert ICCAM error: $result ");
                                } else {
                                    scartLog::logLine("I-[row=$cnt][reportid=$ICCAMreportID] export ICCAM action '$action' (extra=$extra) with reason: " . $reasons[$action]);
                                }

                                sleep(0.5);

                                /*
                                scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                    'record_type' => class_basename($input),
                                    'record_id' => $input->id,
                                    'object_id' => $input->reference,
                                    'action_id' => $actions[$action],
                                    'country' => $country,
                                    'reason' => $reasons[$action],
                                ]);
                                */

                            } else {
                                scartLog::logLine("E-[row=$cnt]; SCART input reference '$input->reference' <> line ICCAM reportID '$ICCAMreportID' ");
                            }

                        } else {

                        }

                    } else {
                        scartLog::logLine("E-[row=$cnt]; not a valid action: '$action'");
                    }

                } else {
                    scartLog::logLine("E-[row=$cnt]; not a valid line: '$inputline'");
                }

            }

            $cnt += 1;

        }



        scartLog::logLine('D-End; cnt='.$cnt);
    }

    // ** STANDALONE ICCAM FUNCTIONS ** //

    public function insertAction($reportID,$actionID,$workuserID,$reason,$country) {

        $record = new \stdClass();
        $record->workuser_id = $workuserID;

        return $this->insertERTaction2ICCAM($reportID,$actionID,$country,$reason);
    }

    function insertERTaction2ICCAM($reportID,$actionID,$country,$reason='SCART API action') {

        $result = '';

        if (Systemconfig::get('abuseio.scart::iccam.active', false)) {

            try {

                if (scartIccam::login()) {

                    scartLog::logLine("D-insertERTaction2ICCAM; export actionID=$actionID for reportID=$reportID ");

                    // set workuser on ICCAM (API) user -> SCART workusers not always ICCAM user
                    $workuser = Systemconfig::get('abuseio.scart::iccam.apiuser', '');
                    $createdate = $this->iccamDate(time());
                    $iccamdata = [
                        'Analyst' => $workuser,
                        'Date' => $createdate,
                    ];
                    // always fill -> errors when empty
                    $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
                    $iccamdata['Country'] = ($country) ? $country : $hotlinecountry;
                    $iccamdata['Reason'] = ($reason) ? $reason : 'SCART API action';
                    $result = scartIccam::insertActionICCAM($reportID, $actionID, $iccamdata);
                    if ($result===false) {
                        $result = 'Error inserting action into ICCAM';
                    } else {
                        $result = '';
                    }

                    scartIccam::close();

                } else {
                    $result = 'Error login into ICCAM';
                }


            } catch (\Exception $err) {

                scartLog::logLine("E-insertERTaction2ICCAM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                $result = "Error exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage();

            }

        }

        return $result;
    }

    function iccamDate($time) {
        $d = date(DATE_ATOM, $time);
        $p = strpos($d,'+');
        if ($p!==false) {
            $d = substr($d , 0, $p) . 'Z';
        }
        return $d;
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
            ['inputfile', 'i', InputOption::VALUE_OPTIONAL, 'Inputfile', ''],
            ['mode', 'm', InputOption::VALUE_OPTIONAL, 'mode', ''],
        ];
    }

}
