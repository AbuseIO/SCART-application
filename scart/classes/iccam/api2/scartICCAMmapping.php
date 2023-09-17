<?php
namespace abuseio\scart\classes\iccam\api2;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\classes\iccam\api2\scartICCAMfields;
use Config;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\models\Input;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\mail\scartAlerts;

class scartICCAMmapping {

    public static function isActive() {
        return Systemconfig::get('abuseio.scart::iccam.active', false);
    }

    /**
     * Special function converting SCART record into ICCAM report
     *
     * @param $record
     * @return bool|string
     */
    public static function insertUpdateICCAM($record) {

        $reportID = '';

        if (self::isActive()) {

            try {

                if ($record->grade_code==SCART_GRADE_IGNORE || $record->grade_code==SCART_GRADE_NOT_ILLEGAL ) {

                    scartLog::logLine("W-insertUpdateICCAM; filenumber=$record->filenumber; grade_code=IGNORE/NOT_ILLEGAL - skip update report");

                    // strange when here -> processed in Grade->onDone()
                    // NOTE: can be because of switch back from ICCAM V3 tot ICCAM v2 mode

                    $reportId = scartICCAMinterface::getICCAMreportID($record->reference);
                    if ($reportId) {

                        scartLog::logLine("D-insertUpdateICCAM; filenumber=$record->filenumber; add ICCAM action NOT-ILLEGAL ");

                        // addAction NotIllegal
                        scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                            'record_type' => class_basename($record),
                            'record_id' => $record->id,
                            'object_id' => $record->reference,
                            'action_id' => SCART_ICCAM_ACTION_NI,     // NOT_ILLEGAL
                            'country' => '',                          // hotline default
                            'reason' => 'SCART reported NI',
                        ]);

                    }

                    $reportID = ' - CLASSIFY IS IGNORE/NOT_ILLEGAL';
                    return $reportID;
                }

                $hostabusecontact = Abusecontact::find($record->host_abusecontact_id);
                if (!$hostabusecontact) {
                    $reportID = ' - HOSTER NOT SET';
                    scartLog::logLine("W-insertUpdateICCAM; filenumber=$record->filenumber; hoster not set - skip update report");
                    return $reportID;
                }

                if (scartICCAM::login()) {

                    $iccamdata = [];

                    scartLog::logLine("D-insertUpdateICCAM; filenumber=$record->filenumber, url='$record->url' ");

                    $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
                    $hostcounty = (scartGrade::isLocal($hostabusecontact->abusecountry) ? $hotlinecountry : $hostabusecontact->abusecountry);

                    // Fill ICCAM classification fields

                    $geticcamfield = 'getSiteTypeID';
                    $iccamdata['SiteTypeID'] = scartICCAMfields::$geticcamfield($record);

                    $questions = Grade_question::fetchClassifyQuestions($record->url_type);
                    if ($questions->count() > 0) {

                        // fill specific ICCAM fields
                        foreach ($questions AS $question) {
                            $geticcamfield = 'get'.$question->iccam_field;
                            $iccamdata[$question->iccam_field] = scartICCAMfields::$geticcamfield($question,$record);
                        }

                    } else {
                        scartLog::logLine("W-insertUpdateICCAM; no ICCAM classification field(s) found? ");
                    }

                    // set workuser on ICCAM (API) user -> SCART workusers not always ICCAM user
                    $workuser = Systemconfig::get('abuseio.scart::iccam.apiuser', '');

                    // based on last updated answer record
                    $ClassificationDate = scartICCAMfields::iccamDate(Grade_question::getGradeTimestamp($record) );

                    // defaults
                    $isVirtual = false;
                    $isChildModeling = false;
                    $isUserGC = false;

                    $iccamdata = array_merge([
                        'Analyst' => $workuser,
                        "Url" => $record->url,
                        "HostingCountry" => $hostcounty,
                        "HostingIP" => $record->url_ip,
                        "HostingNetName" => $hostabusecontact->owner,
                        "Received" => scartICCAMfields::iccamDate(strtotime($record->created_at) ),
                        "ReportingHotlineReference" => $record->filenumber,
                        "HostingHotlineReference" => $hostabusecontact->filenumber,
                        'Memo' => $record->note,
                        "Country" => $hostcounty,
                        "ClassifiedBy" => $workuser,
                        "ClassificationDate" => $ClassificationDate,
                        "IsVirtual" => $isVirtual,
                        "IsChildModeling" => $isChildModeling,
                        "IsUserGC" => $isUserGC,
                    ],$iccamdata);
                    //scartLog::logLine("D-insertUpdateICCAM; iccamdata=".print_r($iccamdata,true));

                    if ($record->reference != '') {
                        $iccamdata['ReportID'] = scartICCAMinterface::getICCAMreportID($record->reference);
                        $reportID = scartICCAM::updscartICCAM($iccamdata);
                        // if empty string, then no error on update
                        if ($reportID==='') $reportID = $iccamdata['ReportID'];
                        $actiontxt= 'update';
                    } else {
                        $reportID = scartICCAM::insscartICCAM($iccamdata);
                        $actiontxt= 'insert';
                    }

                    if (empty($reportID)) {

                        scartLog::logLine("W-insertUpdateICCAM; action=$actiontxt, filenumber='$record->filenumber'; error exporting");

                        $params = [
                            'url' => $record->url,
                            'filenumber' => $record->filenumber,
                        ];
                        scartAlerts::insertAlert(SCART_ALERT_LEVEL_WARNING,'abuseio.scart::mail.scheduler_export_iccam_report_error', $params);

                    } else {
                        scartLog::logLine("D-insertUpdateICCAM; action=$actiontxt, filenumber='$record->filenumber' exported to ICCAM; result=$reportID");
                    }

                    scartICCAM::close();
                } else {
                    $reportID = false;
                }

            } catch (\Exception $err) {
                scartLog::logLine("E-insertUpdateICCAM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }

        return $reportID;
    }

    /**
     *
     * insert ActionID for REPORT into ICCAM
     *
     * @param $reportID
     * @param $actionID
     * @param $workuser_id
     * @param string $country
     * @param string $reason
     */

    public static function insertERTaction2ICCAM($reportID,$actionID,$record,$country,$reason='SCART API action') {

        $result = '';

        // Note; $record was used for workuser, but this is always set on apiuser because not all workusers are user in ICCAM
        // 2022/1/13; possible delete of this parmeter pending on new API 3.0

        if (self::isActive()) {

            try {

                if (scartICCAM::login()) {

                    scartLog::logLine("D-insertERTaction2ICCAM; export actionID=$actionID for reportID=$reportID ");

                    // set workuser on ICCAM (API) user -> SCART workusers not always ICCAM user
                    $workuser = Systemconfig::get('abuseio.scart::iccam.apiuser', '');
                    $createdate = scartICCAMfields::iccamDate(time());
                    $iccamdata = [
                        'Analyst' => $workuser,
                        'Date' => $createdate,
                    ];
                    // always fill -> errors when empty
                    $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
                    $iccamdata['Country'] = ($country) ? $country : $hotlinecountry;
                    $iccamdata['Reason'] = ($reason) ? $reason : 'SCART API action';
                    $result = scartICCAM::insertActionICCAM($reportID, $actionID, $iccamdata);
                    if ($result===false) {
                        $result = 'Error inserting action into ICCAM';
                    } else {
                        $result = '';
                    }

                    scartICCAM::close();

                } else {
                    $result = 'Error login into ICCAM';
                }


            } catch (\Exception $err) {

                scartLog::logLine("E-insertERTaction2ICCAM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                $result = '';

            }

        }

        return $result;
    }

    /**
     *
     * insert ActionID for ITEM into ICCAM
     *
     * @param $reportID
     * @param $actionID
     * @param $workuser_id
     * @param string $country
     * @param string $reason
     */

    public static function insertERTitemAction2ICCAM($itemID,$actionID,$country,$reason='SCART API action') {

        $result = '';

        if (self::isActive()) {

            try {

                if (scartICCAM::login()) {

                    scartLog::logLine("D-insertERTitemAction2ICCAM; export actionID=$actionID for itemID=$itemID ");

                    // API user
                    $workuser = Systemconfig::get('abuseio.scart::iccam.apiuser', '');
                    $createdate = scartICCAMfields::iccamDate(time());
                    $iccamdata = [
                        'Analyst' => $workuser,
                        'Date' => $createdate,
                    ];
                    // always fill -> errors when empty
                    $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
                    $iccamdata['Country'] = ($country) ? $country : $hotlinecountry;
                    $iccamdata['Reason'] = ($reason) ? $reason : 'SCART API action';
                    $result = scartICCAM::insertItemActionICCAM($itemID, $actionID, $iccamdata);
                    if ($result===false) {
                        $result = 'Error inserting action into ICCAM';
                    } else {
                        $result = '';
                    }

                    scartICCAM::close();

                } else {
                    $result = 'Error login into ICCAM';
                }


            } catch (\Exception $err) {

                scartLog::logLine("E-insertERTaction2ICCAM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                $result = '';

            }

        }

        return $result;
    }


    public static function readICCAMfrom($data) {

        $result = '';

        if (self::isActive()) {

            try {

                if (scartICCAM::login()) {

                    $startID = (isset($data['startID'])) ? $data['startID'] : '';
                    $status = (isset($data['status'])) ? '&status='.$data['status'] : '';
                    $origin = (isset($data['origin'])) ? '&origin='.$data['origin'] : '';
                    $max = Systemconfig::get('abuseio.scart::iccam.readimportmax', 10);

                    $result = scartICCAM::read("GetReports?startID=$startID".$status.$origin."&max=$max");
                    if ($result===false) {
                        scartLog::logLine("W-readICCAMfrom; error reading ICCAM");
                    } elseif (!empty($result)) {
                        scartLog::logLine("D-readICCAMfrom; found=".count($result)."; startID=$startID, status=$status, origin=$origin, max=$max " );
                    }

                    scartICCAM::close();
                }

            } catch (\Exception $err) {
                scartLog::logLine("E-readICCAMfrom exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }

        return $result;
    }

    /**
     * data[]
     * - stage; 1=classification, 2=monitoring, 3=completed
     * - startDate
     * - endDate
     *
     * @param $data
     * @return array|false|mixed|string
     */
    public static function readICCAMfromStage($data) {

        $result = false;

        if (self::isActive()) {

            try {

                if (scartICCAM::login()) {

                    $stage = (!empty($data['stage'])) ? '&stage='.$data['stage'] : '&stage='.SCART_ICCAM_REPORTSTAGE_CLASSIFICATON;
                    $startDate = (!empty($data['startDate'])) ? '&startDate='.urlencode($data['startDate']) : '';
                    $endDate = (isset($data['endDate'])) ? '&endDate='.urlencode($data['endDate']) : '';

                    // no max, everything from startDate
                    $max = 0;

                    scartLog::logLine("D-readICCAMfromStage; GetReports?max=$max".$stage.$startDate.$endDate );
                    $result = scartICCAM::read("GetReports?max=$max".$stage.$startDate.$endDate);
                    if (($result===false) || (isset($result->Message))) {
                        scartLog::logLine("W-readICCAMfromStage; error reading ICCAM; message=" . (isset($result->Message) ? $result->Message : '?') );
                        $result = false;
                    } elseif (!empty($result)) {
                        if (!is_array($result)) {
                            scartLog::logLine("W-readICCAMfromStage; unknown result=".print_r($result,true) );
                            $result = false;
                        }
                    }

                    scartICCAM::close();
                }

            } catch (\Exception $err) {
                scartLog::logLine("E-readICCAMfromStage exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }

        return $result;
    }

    public static function readICCAMlastReportID() {

        $lastReportID = '';
        if (self::isActive()) {

            try {

                if (scartICCAM::login()) {

                    // read all reports with max of 200 rows -> first is last ReportID
                    $result = scartICCAM::read("GetReports");
                    if ($result===false) {
                        scartLog::logLine("W-readICCAMlastReportID; error reading ICCAM");
                    } else {
                        $lastReportID = (count($result) > 1) ? $result[0]->ReportID : $result;
                        scartLog::logLine("D-readICCAMlastReportID; lastReportID=$lastReportID ");
                    }

                    scartICCAM::close();
                }

            } catch (\Exception $err) {
                scartLog::logLine("E-readICCAMfrom exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }
        return $lastReportID;
    }

    public static function readICCAMreportID($reportID) {

        $result = '';
        if (self::isActive()) {

            try {

                if (scartICCAM::login()) {

                    $result = scartICCAM::read("GetReports?id=$reportID");
                    if ($result===false) {
                        scartLog::logLine("W-readICCAMlastReportID($reportID); error reading ICCAM");
                    } else {
                        if (is_array($result)) {
                            $result = (count($result) > 1) ? $result[0] : '';
                        }
                        $readreportID = ($result) ? $result->ReportID : '?';
                        scartLog::logLine("D-readICCAMreportID($reportID); readReportID=$readreportID ");
                    }

                    scartICCAM::close();
                }

            } catch (\Exception $err) {
                scartLog::logLine("E-readICCAMfrom exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }
        return $result;
    }

    public static function alreadyImportedICCAMreportID($reportID) {

        // check if already loaded
        $refReportID = scartICCAMinterface::setICCAMreportID($reportID);
        return ( (Input::where('reference',$refReportID)->count() > 0) );
    }

    public static function alreadyActionsSetICCAMreportID($reportID , $reportall=false) {

        try {

            $txt = '';

            if (scartICCAM::login()) {
                // check if already action(s) set
                $actions = scartICCAM::readActionsICCAM($reportID);
                if (count($actions) > 0) {
                    foreach ($actions AS $action) {
                        if (!$reportall && in_array($action->ActionID,[SCART_ICCAM_ACTION_LEA,SCART_ICCAM_ACTION_ISP,SCART_ICCAM_ACTION_MO])) {
                            // check if localcountry analist
                            $arr = explode('.',$action->Analyst);
                            $ext = strtolower(end($arr));
                            $local = Systemconfig::get('abuseio.scart::classify.detect_country', '');
                            $local = explode(',',$local);
                            if ($ext && in_array($ext,$local)) {
                                if ($txt!='') $txt .= ',';
                                $actiontxt = (isset(scartICCAMfields::$actionMap[$action->ActionID]) ? scartICCAMfields::$actionMap[$action->ActionID] : 'unknown');
                                $txt .= "action=$actiontxt (analyst=$action->Analyst)";
                            }
                            // if not country then allowed
                        } else {
                            // reportall
                            if ($txt!='') $txt .= ',';
                            $actiontxt = (isset(scartICCAMfields::$actionMap[$action->ActionID]) ? scartICCAMfields::$actionMap[$action->ActionID] : 'unknown');
                            $txt .= "action=$actiontxt (analyst=$action->Analyst)";
                        }

                    }
                    scartLog::logLine("D-alreadyActionsSetICCAMreportID ($reportID) found: $txt"  );
                }
                scartICCAM::close();
            }

        } catch (\Exception $err) {
            scartLog::logLine("E-alreadyActionsSetICCAMreportID exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
        }
        return $txt;
    }

    public static function alreadyItemActionsSetICCAMreportID($itemID , $reportall=false) {

        try {

            $txt = '';

            if (scartICCAM::login()) {
                // check if already action(s) set
                $actions = scartICCAM::readItemActionsICCAM($itemID);
                if (count($actions) > 0) {
                    foreach ($actions AS $action) {
                        if (!$reportall && in_array($action->ActionID,[SCART_ICCAM_ACTION_LEA,SCART_ICCAM_ACTION_ISP,SCART_ICCAM_ACTION_MO])) {
                            // check if .NL analist
                            $arr = explode('.',$action->Analyst);
                            $ext = strtolower(end($arr));
                            $local = Systemconfig::get('abuseio.scart::classify.detect_country', '');
                            $local = explode(',',$local);
                            if ($ext && in_array($ext,$local)) {
                                if ($txt!='') $txt .= ',';
                                $actiontxt = (isset(scartICCAMfields::$actionMap[$action->ActionID]) ? scartICCAMfields::$actionMap[$action->ActionID] : 'unknown');
                                $txt .= "action=$actiontxt (analyst=$action->Analyst)";
                            }
                            // if not .NL then allowed
                        } else {
                            // reportall
                            if ($txt!='') $txt .= ',';
                            $actiontxt = (isset(scartICCAMfields::$actionMap[$action->ActionID]) ? scartICCAMfields::$actionMap[$action->ActionID] : 'unknown');
                            $txt .= "action=$actiontxt (analyst=$action->Analyst)";
                        }

                    }
                    //scartLog::logLine("D-alreadyItemActionsSetICCAMreportID ($itemID) found: $txt"  );
                }
                scartICCAM::close();
            }

        } catch (\Exception $err) {
            scartLog::logLine("E-alreadyItemActionsSetICCAMreportID exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
        }
        return $txt;
    }



}
