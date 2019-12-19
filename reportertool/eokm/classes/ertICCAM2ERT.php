<?php

/**
 * ICCAM2ERT class
 *
 * ERT mapping to ICCAM functions
 *
 */

namespace reportertool\eokm\classes;

use Config;
use reportertool\eokm\classes\ertMail;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Grade_answer;
use ReporterTool\EOKM\Models\Grade_question;

class ertICCAM2ERT {

    public static $_ICCAMreference = ' (ICCAM)';

    public static function isActive() {
        return Config::get('reportertool.eokm::iccam.active', false);
    }

    /** ERT mapping to ICCAM  **/

    public static $actionMap = [
        1 => 'LEA',
        2 => 'ISP',
        3 => 'Content Removed (CR)',
        4 => 'Content Unavailable (CU)',
        5 => 'Moved (MO)',
        7 => 'Not Illegal (NI)',
    ];
    public static $siteTypeMap = [
        'notdetermined' => 1,
        'website' => 2,
        'filehost' => 3,
        'imagestore' => 4,
        'imageboard' => 5,
        'forum' => 6,
        'bannersite' => 7,
        'linksite' => 8,
        'socialsite' => 9,
        'redirector' => 10,
        'webarchived' => 11,
        'searchprovider' => 18,
        'imagehost' => 20,
        'blog' => 22,
        'webpage' => 23,
    ];

    static private $_genderMap = 'sex';
    static private $_genderOptionMap = [
        'UN' => 1,          // Undetermined
        'FE'=> 2,           // Female
        'MA' => 3,          // Male
        'FEMA' => 4,        // Both
    ];
    static private $_ageGroupMap = 'age';
    static private $_ageGroupOptionMap = [
        'ND' => 1,          // Not Determined
        'IN' => 2,          // Infant
        'PP' => 3,          // Pre-pubescent
        'PU' => 4,          // Pubescent
    ];
    static private $_classificationMap = 'punishable';
    static private $_classificationOptionMap = [
        'BA' => 1,                // Baseline SCAM
        'NA' => 2,                // National SCAM
    ];
    static private $_classificationNotIllegal = 4;     // Not illegal (ignore)

    public static function iccamDate($time) {
        $d = date(DATE_ATOM, $time);
        $p = strpos($d,'+');
        if ($p!==false) {
            $d = substr($d , 0, $p) . 'Z';
        }
        return $d;
    }

    public static function getICCAMreportID($reference) {
        return trim(str_replace(self::$_ICCAMreference,'',$reference));
    }

    public static function setICCAMreportID($reportID) {
        return $reportID. self::$_ICCAMreference;
    }
    static function getGradeAnswer($map,$record,$isInput) {

        $gradeQuestion = Grade_question::where('questiongroup', ERT_GRADE_QUESTION_GROUP_ILLEGAL)
            ->where('name', $map)
            ->first();
        if ($gradeQuestion) {
            $answerOption = Grade_answer::where('record_type', ($isInput) ? 'input' : 'notification')
                ->where('record_id', $record->id)
                ->where('grade_question_id', $gradeQuestion->id)
                ->first();
        } else {
            $answerOption = '';
        }
        return ($answerOption) ? unserialize($answerOption->answer) : array();
    }
    static function getGradeTimestamp() {
        // on genderMap
        $gradeQuestion = Grade_question::where('questiongroup', ERT_GRADE_QUESTION_GROUP_ILLEGAL)
            ->where('name', self::$_genderMap)
            ->first();
        return ($gradeQuestion) ? strtotime($gradeQuestion->updated_at) : time();
    }

    /**
     * Special function converting ERT record (input or notification) into ICCAM report
     *
     *
     * @param $record
     * @return bool|string
     */
    public static function insertERT2ICCAM($record) {

        $reportID = '';

        if (self::isActive()) {

            try {

                if ($record->grade_code==ERT_GRADE_IGNORE || $record->grade_code==ERT_GRADE_NOT_ILLEGAL ) {
                    $reportID = '(SKIP IGNORE/NOT_ILLEGAL FOR ICCAM)';
                    ertLog::logLine("W-insertERT2ICCAM; record grade_code=IGNORE - skip this one");
                    return $reportID;
                }

                if (ertICCAM::login()) {

                    ertLog::logLine("D-insertERT2ICCAM; export '$record->url' ");

                    $isInput = (class_basename($record) == 'Input');

                    $hostabusecontact = Abusecontact::find($record->host_abusecontact_id);
                    $hostcounty = (ertGrade::isNL($hostabusecontact->abusecountry) ? 'NL' : $hostabusecontact->abusecountry);
                    $siteTypeID = (isset(self::$siteTypeMap[$record->type_code])) ? self::$siteTypeMap[$record->type_code] : self::$siteTypeMap['notdetermined'];

                    $answerGender = self::getGradeAnswer(self::$_genderMap,$record,$isInput);
                    $gender = '';
                    if (in_array('UN', $answerGender)) {
                        $gender = 'UN';
                    } else {
                        if (in_array('FE', $answerGender)) {
                            $gender .= 'FE';
                        }
                        if (in_array('MA', $answerGender)) {
                            $gender .= 'MA';
                        }
                    }
                    $genderID = (isset(self::$_genderOptionMap[$gender])) ? self::$_genderOptionMap[$gender] : self::$_genderOptionMap['UN'];
                    ertLog::logLine("D-genderQuestion; answerGender=".implode('/',$answerGender).", gender=$gender (genderID=$genderID), ");

                    $answerAge = self::getGradeAnswer(self::$_ageGroupMap,$record,$isInput);
                    $age = '';
                    if (in_array('IN', $answerAge)) {
                        $age = 'IN';
                    }
                    if (in_array('PP', $answerAge)) {
                        $age = 'PP';
                    }
                    if (in_array('PU', $answerAge)) {
                        $age = 'PU';
                    }
                    $ageID = (isset(self::$_ageGroupOptionMap[$age])) ? self::$_ageGroupOptionMap[$age] : self::$_ageGroupOptionMap['ND'];
                    ertLog::logLine("D-ageQuestion; answerAge=".implode('/',$answerAge).", age=$age (ageID=$ageID), ");

                    if ($record->grade_code==ERT_GRADE_ILLEGAL) {
                        $answerClassification = self::getGradeAnswer(self::$_classificationMap,$record,$isInput);
                        $answerClassification = ($answerClassification) ? implode('',$answerClassification) : '';
                        $ClassificationID = (isset(self::$_classificationOptionMap[$answerClassification])) ? self::$_classificationOptionMap[$answerClassification] : self::$_classificationOptionMap['BA'];
                    } else {
                        $ClassificationID = self::$_classificationNotIllegal;
                        $answerClassification = ERT_GRADE_NOT_ILLEGAL;
                    }
                    ertLog::logLine("D-classificationQuestion; answerClassification=$answerClassification, ClassificationID=$ClassificationID ");

                    $workuser = ertUsers::getWorkuserLogin($record->workuser_id);
                    if ($workuser == ertUsers::$_unknown) {
                        // mist be valid ICCAM user -> fallback on API user
                        $workuser = Config::get('reportertool.eokm::iccam.apiuser', '');
                    }

                    // base on last update answer Gender
                    $ClassificationDate = self::iccamDate(self::getGradeTimestamp() );
                    $isVirtual = false;
                    $isChildModeling = false;

                    $iccamdata = [
                        'Analyst' => $workuser,
                        "Url" => $record->url,
                        "HostingCountry" => $hostcounty,
                        "HostingIP" => $record->url_ip,
                        "HostingNetName" => $hostabusecontact->owner,
                        "Received" => self::iccamDate(strtotime($record->created_at) ),
                        "ReportingHotlineReference" => $record->filenumber,
                        "HostingHotlineReference" => $hostabusecontact->filenumber,
                        'Memo' => (($record->note)?$record->note:"Upload from ERT at " .date('Y-m-d H:i:s')),
                        "Country" => $hostcounty,
                        "ClassifiedBy" => $workuser,
                        'ClassificationID' => $ClassificationID,
                        "ClassificationDate" => $ClassificationDate,
                        "SiteTypeID" => $siteTypeID,
                        "GenderID" => $genderID,
                        "AgeGroupID" => 1,
                        "IsVirtual" => $isVirtual,
                        "IsChildModeling" => $isChildModeling,
                    ];

                    $reportID = ertICCAM::insertICCAM($iccamdata);

                    if ($reportID==false) {
                        ertLog::logLine("E-insertERT2ICCAM; error inserting filenumber '$record->filenumber' into ICCAM; url already imported!?");
                    } else {
                        ertLog::logLine("D-insertERT2ICCAM; inserted filenumber '$record->filenumber' into ICCAM; reportID=$reportID");
                    }

                    ertICCAM::close();
                }

            } catch (\Exception $err) {
                ertLog::logLine("E-insertERT2ICCAM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }

        return $reportID;
    }

    /**
     *
     * insert ActionID into ICCAM
     *
     * @param $reportID
     * @param $actionID
     * @param $workuser_id
     * @param string $country
     * @param string $reason
     */

    public static function insertERTaction2ICCAM($reportID,$actionID,$workuser_id,$country='NL',$reason='ERT API action') {

        $result = '';

        if (self::isActive()) {

            try {

                if (ertICCAM::login()) {

                    ertLog::logLine("D-insertERTaction2ICCAM; export actionID=$actionID for reportID=$reportID ");

                    $workuser = ertUsers::getWorkuserLogin($workuser_id);
                    if ($workuser == ertUsers::$_unknown) {
                        // mist be valid ICCAM user -> fallback on API user
                        $workuser = Config::get('reportertool.eokm::iccam.apiuser', '');
                    }
                    $createdate = self::iccamDate(time());
                    $iccamdata = [
                        'Analyst' => $workuser,
                        'Date' => $createdate,
                    ];
                    // always fill -> errors when empty
                    $iccamdata['Country'] = ($country) ? $country : 'NL';
                    $iccamdata['Reason'] = ($reason) ? $reason : 'Insert by ERT ICCAM interface';
                    $result = ertICCAM::insertActionICCAM($reportID, $actionID, $iccamdata);
                    if ($result===false) {
                        ertLog::logLine("W-insertERTaction2ICCAM; error inserting actionID=$actionID for reportID=$reportID; report already closed!?");

                        // warning to operator
                        $params = [
                            'reportID' => $reportID,
                            'action' => (isset(self::$actionMap[$actionID]) ? self::$actionMap[$actionID] : '(unknown?)'),
                        ];
                        ertAlerts::insertAlert(ERT_ALERT_LEVEL_WARNING,'reportertool.eokm::mail.scheduler_iccam_set_action_error', $params);

                    }

                    ertICCAM::close();
                }


            } catch (\Exception $err) {

                ertLog::logLine("E-insertERTaction2ICCAM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

                $result = false;

            }

        }

        return $result;
    }


    public static function readICCAMfrom($data) {

        $result = '';

        if (self::isActive()) {

            try {

                if (ertICCAM::login()) {

                    $startID = (isset($data['startID'])) ? $data['startID'] : '';
                    $status = (isset($data['status'])) ? $data['status'] : '';
                    $origin = (isset($data['origin'])) ? $data['origin'] : '';
                    $max = Config::get('reportertool.eokm::iccam.readimportmax', 10);

                    $result = ertICCAM::read("GetReports?startID=$startID&status=$status&origin=$origin&max=$max");
                    if ($result===false) {
                        ertLog::logLine("W-readICCAMfrom; error reading ICCAM");
                    } else {
                        ertLog::logLine("D-readICCAMfrom; found=".count($result)."; startID=$startID, status=$status, origin=$origin, max=$max " );
                    }

                    ertICCAM::close();
                }

            } catch (\Exception $err) {
                ertLog::logLine("E-readICCAMfrom exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }

        return $result;
    }

    public static function readICCAMlastReportID() {

        $lastReportID = '';
        if (self::isActive()) {

            try {

                if (ertICCAM::login()) {

                    // read all reports with max of 200 rows -> first is last ReportID
                    $result = ertICCAM::read("GetReports");
                    if ($result===false) {
                        ertLog::logLine("W-readICCAMlastReportID; error reading ICCAM");
                    } else {
                        $lastReportID = (count($result) > 1) ? $result[0]->ReportID : $result;
                        ertLog::logLine("D-readICCAMlastReportID; lastReportID=$lastReportID ");
                    }

                    ertICCAM::close();
                }

            } catch (\Exception $err) {
                ertLog::logLine("E-readICCAMfrom exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }

        }
        return $lastReportID;
    }

    public static function alreadyImportedICCAMreportID($reportID) {

        // check if already loaded
        $refReportID = self::setICCAMreportID($reportID);
        return ((Notification::where('reference',$refReportID)->count() > 0) || (Input::where('reference',$refReportID)->count() > 0) );
    }

    public static function alreadyActionsSetICCAMreportID($reportID) {

        $actions = [];
        try {
            if (ertICCAM::login()) {
                // check if already loaded
                $actions = ertICCAM::readActionsICCAM($reportID);
                if (count($actions) > 0) {
                    $txt = '';
                    foreach ($actions AS $action) {
                        if ($txt!='') $txt .= ',';
                        $txt .= "actionID=$action->ActionID";
                    }
                    ertLog::logLine("D-readICCAMlastReportID; alreadyActionsSetICCAMreportID ($reportID) found: $txt"  );
                }
                ertICCAM::close();
            }

        } catch (\Exception $err) {
            ertLog::logLine("E-alreadyActionsSetICCAMreportID exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
        }
        return (count($actions) > 0);
    }



}
