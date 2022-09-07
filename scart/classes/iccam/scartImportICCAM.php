<?php
namespace abuseio\scart\classes\iccam;

use abuseio\scart\models\Grade_question;
use Config;
use abuseio\scart\Plugin;
use abuseio\scart\models\ImportExport_job;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_extrafield;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;

class scartImportICCAM {

    public static function doImport($scheduler_process_count) {

        try {

            // check general if ICCAM is active
            if (Systemconfig::get('abuseio.scart::iccam.active', false)) {

                /**
                 * 2021/1/27/Gs: changed from importing based on last reportID to importing based on stageDate from classify/monitor ICCAM reports
                 *
                 */

                //self::importFromLastID($scheduler_process_count);
                self::importJobLastDate($scheduler_process_count);

            }

        } catch (\Exception $err) {

            scartLog::logLine("E-scartImportICCAM; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

    }

    static function importJobLastDate($scheduler_process_count) {

        $iccamtimeframe = 30;   // interval in minutes; 240, 120, 60, 30

        if ($iccamtimeframe < 60) {
            if (date('i') < 30) {
                $currentdate = date('Y-m-d H:00:00');
            } else {
                $currentdate = date('Y-m-d H:30:00');
            }
        } else {
            $currentdate = date('Y-m-d H:00:00');
        }

        if ( !($lastdate = self::getImportlast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE)) ) {
            $lastdate = $currentdate;
            self::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE,$lastdate);
            scartLog::logLine("D-scartImportICCAM.importFromLastDate; init lastDate on: $lastdate");
        } else {
            scartLog::logLine("D-scartImportICCAM.importFromLastDate; currentdate=$currentdate, lastDate=$lastdate");
        }

        if ($lastdate) {

            $reports = self::importFromLastdate($lastdate,$iccamtimeframe);

            if ($lastdate < $currentdate) {
                // next call next hour
                $lastdate = date('Y-m-d H:i:00', strtotime('+'.$iccamtimeframe.' minutes', strtotime($lastdate)));
                scartLog::logLine("D-scartImportICCAM.importFromLastDate; currentdate=$currentdate, set lastDate on next hour: $lastdate");
                self::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE, $lastdate);
            }

            if (count($reports) > 0) {

                // report JOB
                $params = [
                    'reports' => $reports,
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO, 'abuseio.scart::mail.scheduler_import_iccam', $params);

            } elseif (count($reports) == 0) {

                if (scartICCAM::isOffline()) {
                    scartLog::logLine("W-scartImportICCAM.importFromLastDate; ICCAM OFFLINE!?");
                }

            }

        } else {
            scartLog::logLine("W-scartImportICCAM.importFromLastDate; cannot find last reportID !?");
        }

    }

    public static function importFromLastdate($lastdate,$iccamtimeframe,$setReceivedICCAM=false) {

        /**
         * read from stage;
         * - stage: 1=classification, 2=monitoring, 3=completed
         * - startDate: Y-m-d
         *
         * Note: $scheduler_process_count not used -> read all records of one day
         *
         */

        $reports = [];

        $reportStages = [SCART_ICCAM_REPORTSTAGE_CLASSIFICATON,SCART_ICCAM_REPORTSTAGE_MONITOR];

        foreach ($reportStages AS $reportStage) {

            // read reports from lastdate

            // get last hour before lastdate -> report can be inserted from different timezone
            // use UTC for ICCAM reading
            $startdate = gmdate('Y-m-d H:i:00', strtotime('-'.$iccamtimeframe.' min', strtotime($lastdate)));
            $enddate = gmdate('Y-m-d H:i:00', strtotime($lastdate));
            $iccamreports = scartICCAMmapping::readICCAMfromStage([
                'stage' => $reportStage,
                'startDate' => $startdate,
                'endDate' => $enddate,
            ]);
            //scartLog::logLine("D-scartImportICCAM.importFromLastDate.readiccam; iccamreports=" . print_r($iccamreports,true));

            if (!$iccamreports) {
                // error of geen reports -> if error then logged
                // set  empty array for workflow logging
                $iccamreports = [];
            }

            if (is_array($iccamreports)) {

                scartLog::logLine("D-scartImportICCAM.importFromLastDate.readiccam [stage=$reportStage]; lastdate=$lastdate; get.gmdate($startdate till $enddate); count(reports)=".count($iccamreports) );

                if (count($iccamreports) >= 1000) {

                    // max reached

                    // (ONE TIME) inform admin
                    $params = [
                        'reportname' => 'ICCAM import count='.count($iccamreports),
                        'report_lines' => [
                            "stage=$reportStage, startDate=$startdate, enddate=$enddate"
                        ]
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                }

                if (count($iccamreports) > 0) {

                    $cnt = 1;
                    foreach ($iccamreports as $iccamreport) {

                        $loglines = [];
                        $status_timestamp = '[' . date('Y-m-d H:i:s') . '] ';

                        // general report fields
                        $reportID = $iccamreport->ReportID;
                        $note = (!empty($iccamreport->Memo)) ? $iccamreport->Memo : '';
                        $refererUrl = (isset($iccamreport->RefererUrl) ? $iccamreport->RefererUrl : '');
                        $HotlineID = $iccamreport->HotlineID;
                        $Analyst = $iccamreport->Analyst;
                        $iccamReceived = (isset($iccamreport->Received) ? $iccamreport->Received : '');

                        //scartLog::logLine("D-scartImportICCAM.importFromLastDate; got reportID=$reportID - analyze");

                        // check if not already in SCART
                        if (!scartICCAMmapping::alreadyImportedICCAMreportID($reportID)) {

                            // check if not already has actions
                            $txt = scartICCAMmapping::alreadyActionsSetICCAMreportID($reportID);
                            if ($txt == '') {

                                // check if siteType is set -> convert to SCART siteType value
                                $siteTypeId = (isset($iccamreport->SiteTypeID)) ? $iccamreport->SiteTypeID : scartICCAMfields::$siteTypeIDNotDetermined;
                                $siteTypeId = (is_numeric($siteTypeId)) ? intval($siteTypeId) : scartICCAMfields::$siteTypeIDNotDetermined;
                                $key = array_search($siteTypeId, scartICCAMfields::$SiteTypeIDMap);
                                $type_code = ($key !== false) ? $key : SCART_ICCAM_IMPORT_TYPE_CODE_ICCAM;

                                $first = true;
                                foreach ($iccamreport->Items as $item) {

                                    $itemID = $item->ID;

                                    // check if ITEM not already has actions
                                    $txt = scartICCAMmapping::alreadyItemActionsSetICCAMreportID($itemID);
                                    if ($txt == '') {

                                        // get (first) Assessments  (clasification of item)

                                        $classificationID = $genderID = $agegroupID = $assessments = false;
                                        if (is_array($item->Assessments)) {
                                            reset($item->Assessments);
                                            $assessments = current($item->Assessments);
                                            if ($assessments) {
                                                // pick first as ICCAM classification
                                                $classificationID = (isset($assessments->ClassificationID)?$assessments->ClassificationID:false);
                                                if (!empty($assessments->ClassifiedBy)) $Analyst = $assessments->ClassifiedBy;
                                            }
                                        }

                                        if ($classificationID != scartICCAMfields::$_classificationNotIllegal) {

                                            scartLog::logLine("D-scartImportICCAM.importFromLastDate; reportID=$reportID; found import url '$item->Url' with classificationID=$classificationID ");

                                            if (Input::where('url', $item->Url)->count() == 0) {

                                                try {

                                                    $input = new Input();
                                                    $input->url = $item->Url;
                                                    $input->url_type = SCART_URL_TYPE_MAINURL;

                                                    // only the first get reportID -> import other items with reference = empty
                                                    // -> then this will lead to an new export to ICCAM with new reportID

                                                    if ($first) {
                                                        $input->note = $note;
                                                        $input->reference = scartICCAMfields::setICCAMreportID($reportID);
                                                        $first = false;  // reset
                                                    } else {
                                                        $input->note = (!empty($item->Memo)) ? $item->Memo : '';
                                                        $input->reference = '';
                                                    }

                                                    // 2021/2/12/Gs referer url in iccam interface
                                                    $input->url_referer = $refererUrl;

                                                    $input->status_code = SCART_STATUS_SCHEDULER_SCRAPE;
                                                    $input->workuser_id = 0;
                                                    $input->type_code = $type_code;
                                                    $input->source_code = SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM;

                                                    if ($setReceivedICCAM) {
                                                        if ($iccamReceived && (strtotime($iccamReceived) !== false) ) {
                                                            $input->received_at = date('Y-m-d H:i:s',strtotime($iccamReceived));
                                                        } else {
                                                            ertLog::logLine("W-ertImportICCAM.importFromLastDate; ICCAM received time NOT set/correct '$iccamReceived' - set current time");
                                                            $input->received_at = date('Y-m-d H:i:s');
                                                        }
                                                        ertLog::logLine("D-ertImportICCAM.importFromLastDate; setReceivedICCAM; received_at=$input->received_at (iccamreceived=$iccamReceived) ");
                                                    } else {
                                                        $input->received_at = date('Y-m-d H:i:s');
                                                    }

                                                    $input->save();

                                                    $input->generateFilenumber();
                                                    $input->save();

                                                    if ($input->reference) {
                                                        $input->logHistory(SCART_INPUT_HISTORY_ICCAM,'',$reportID,'Direct import from ICCAM');
                                                    }

                                                    // log old/new for history
                                                    $input->logHistory(SCART_INPUT_HISTORY_STATUS,'',SCART_STATUS_SCHEDULER_SCRAPE,"Import item from ICCAM");

                                                    // 2021/2/12/Gs: extra ICCAM iccamreport fields
                                                    $input->addExtrafield( SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_HOTLINEID,$HotlineID);
                                                    $input->addExtrafield( SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_ANALYST,$Analyst);

                                                    $input->logText("Added url '$item->Url' (reference ICCAM reportID=$reportID)");

                                                    $loglines[] = $status_timestamp .
                                                        "import url '$item->Url'; filenumber=$input->filenumber, type=$type_code, reportID=$reportID, received=$input->received_at";

                                                    $classification = array_search($classificationID,scartICCAMfields::$ClassificationIDMap);
                                                    if ($classification) {

                                                        // mark classification from ICCAM
                                                        $input->addExtrafield(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION,'yes');

                                                        // import classification

                                                        $questions = Grade_question::fetchClassifyQuestions($input->url_type);
                                                        if ($questions->count() > 0) {
                                                            // fill specific ICCAM fields
                                                            foreach ($questions AS $question) {
                                                                $iccamfield = $question->iccam_field;
                                                                // SiteTypeID already done above
                                                                if ($iccamfield != 'SiteTypeID') {
                                                                    $iccamvalue = (isset($assessments->$iccamfield)?$assessments->$iccamfield:'');
                                                                    scartICCAMfields::setClassificationICCAMfield($question,$input,$iccamvalue);
                                                                }
                                                            }
                                                            $input->logText("copied classification fields from ICCAM");
                                                            scartLog::logLine("D-scartImportICCAM.importFromLastDate; copied ICCAM classification (ILLEGAL) to SCART record (filenumber=$input->filenumber) ");
                                                        } else {
                                                            scartLog::logLine("W-scartImportICCAM.importFromLastDate; no ICCAM classification field(s) found? ");
                                                        }

                                                        $input->grade_code = SCART_GRADE_ILLEGAL;
                                                        $input->save();

                                                    } else {

                                                        $input->addExtrafield(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION,'no');

                                                    }

                                                } catch (\Exception $err) {

                                                    $loglines[] = $status_timestamp . "error inserting into SCART; Report item url=$item->Url - SKIP - error message: " . $err->getMessage();
                                                    scartLog::logLine("W-scartImportICCAM.importFromLastDate; reportID=$reportID; itemID $itemID; error inserting into SCART, SKIP this one; message: " . $err->getMessage());

                                                }

                                            } else {

                                                scartLog::logLine("D-scartImportICCAM.importFromLastDate; reportID=$reportID; itemID $itemID with url '$item->Url' already exists in SCART");

                                                // got SCART record with same url

                                                $input = Input::where('url', $item->Url)->first();
                                                if ($input) {

                                                    if (!in_array($input->status_code,[
                                                        SCART_STATUS_OPEN,
                                                        SCART_STATUS_SCHEDULER_SCRAPE,
                                                        SCART_STATUS_CANNOT_SCRAPE,
                                                        SCART_STATUS_WORKING,
                                                        SCART_STATUS_GRADE,
                                                    ])) {

                                                        // SCART Classification done

                                                        if ($reportID2 = scartICCAMfields::getICCAMreportID($input->reference)) {

                                                            //  ICCAM reportID reference is set in SCART
                                                            // read action from ICCAM

                                                            $actions = scartICCAM::getActionsICCAM($reportID2);
                                                            if (count($actions) > 0) {

                                                                // found actions for this SCART record -> report to ICCAM for this item
                                                                foreach ($actions AS $action) {
                                                                    // $action->ActionID
                                                                    scartICCAMmapping::insertERTitemAction2ICCAM($itemID,$action->ActionID,$action->Country);
                                                                }

                                                                $loglines[] = $status_timestamp . "url '$item->Url' already exists in SCART";

                                                                scartLog::logLine("D-scartImportICCAM.importFromLastDate; reportID=$reportID; already exists in SCART; copied actions to itemID '$itemID' ");
                                                                $loglines[] = $status_timestamp . "copied actions of SCART filenumber '$input->filenumber' with reportID '$reportID2' to ICCAM for itemID '$itemID' (reportID=$reportID) ";

                                                            }
                                                        }

                                                    }

                                                    // not ICCAM reference in SCART set, then set on this one

                                                    if (empty($input->reference)) {

                                                        scartLog::logLine("D-scartImportICCAM.importFromLastDate; reportID=$reportID; input '$input->filenumber' reference empty, set on ICCAM $reportID");

                                                        $input->note = $note;
                                                        $input->reference = scartICCAMfields::setICCAMreportID($reportID);
                                                        $input->source_code = SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM;
                                                        $input->save();

                                                        $input->logText("found ICCAM reportID for this url; set reference on $reportID");
                                                        $loglines[] = $status_timestamp . "url '$item->Url' exists in SCART with empty reference; reference in SCART set on ICCAM '$reportID'";

                                                    }

                                                }

                                            }

                                        } else {

                                            scartLog::logLine("D-scartImportICCAM.importFromLastDate[cnt=$cnt]; reportID=$reportID; classification=IGNORE from report '$item->Url' - SKIP");

                                        }

                                    } else {

                                        // do not log, repeating for every report on one day
                                        scartLog::logLine("D-scartImportICCAM.importFromLastDate[cnt=$cnt]; reportID=$reportID; itemID '$itemID' has already ACTIONS ($txt) set in ICCAM - SKIP");

                                    }

                                }

                            } else {

                                // do not log, repeating for every report on one day
                                //$loglines[] = $status_timestamp . "import reportID '$reportID' has already ACTIONS set in ICCAM ($txt) - SKIP";
                                scartLog::logLine("D-scartImportICCAM.importFromLastDate[cnt=$cnt]; reportID '$reportID' has already ACTIONS ($txt) set in ICCAM - SKIP");

                            }

                        } else {
                            //$loglines[] = "reportID '$lastID' already imported into SCART";
                            scartLog::logLine("D-scartImportICCAM.importFromLastDate[cnt=$cnt]; reportID=$reportID already imported into SCART - SKIP");
                        }

                        if (count($loglines) > 0) {

                            $reports[] = [
                                'reportID' => $reportID,
                                'loglines' => $loglines
                            ];

                        }

                        $cnt += 1;

                    }

                }

            }

        }

        return $reports;
    }

    // ** Obsolute? **

    static function importFromLastID($scheduler_process_count) {

        if ( !($lastID = self::getImportlast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLAST)) ) {
            $lastID = scartICCAMmapping::readICCAMlastReportID();
            self::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLAST,$lastID);
        }

        if ($lastID) {

            // 2020/5/29/Gs: sort not ascending
            $maxID = $lastID;

            // get reports since last reportID

            // Note: $scheduler_process_count not used -> readICCAMfrom has max of (iccam.readimportmax) each time

            $iccamreports = scartICCAMmapping::readICCAMfrom([
                'startID' => $lastID,
                'status' => SCART_ICCAM_REPORTSTATUS_EITHER,
                'origin' => SCART_ICCAM_REPORTORIGIN_USERCOUNTRY,
            ]);
            if ($iccamreports && (count($iccamreports) > 0) ) {

                $reports = [];

                foreach ($iccamreports as $iccamreport) {

                    $loglines = [];
                    $status_timestamp = '['.date('Y-m-d H:i:s').'] ';

                    $lastID = $iccamreport->ReportID;
                    if ($lastID > $maxID) $maxID = $lastID;
                    $note = (!empty($iccamreport->Memo)) ? $iccamreport->Memo : '';

                    // check if not already in ERT
                    if (!scartICCAMmapping::alreadyImportedICCAMreportID($lastID)) {

                        $txt = scartICCAMmapping::alreadyActionsSetICCAMreportID($lastID);
                        if ($txt == '') {

                            // check if siteType is set -> convert to SCART siteType value
                            $siteTypeId = (isset($iccamreport->SiteTypeID)) ? $iccamreport->SiteTypeID : 1;
                            $siteTypeId = (is_numeric($siteTypeId)) ? intval($siteTypeId) : 1;
                            $key = array_search($siteTypeId,scartICCAMfields::$SiteTypeIDMap);
                            $type_code = ($key!==false) ? $key : SCART_ICCAM_IMPORT_TYPE_CODE_ICCAM;

                            $first = true;
                            foreach ($iccamreport->Items AS $item) {

                                scartLog::logLine("D-scartImportICCAM; found url '$item->Url' to import ");

                                if (Input::where('url',$item->Url)->count() == 0) {

                                    try {

                                        $input = new Input();
                                        $input->url = $item->Url;
                                        $input->url_type = SCART_URL_TYPE_MAINURL;

                                        // only the first get reportID -> import other items with reference = empty
                                        // -> then this will lead to an new export to ICCAM with new reportID

                                        if ($first) {
                                            $input->note = $note;
                                            $input->reference = scartICCAMfields::setICCAMreportID($lastID);
                                            $first = false;  // reset
                                        } else {
                                            $input->note = (!empty($item->Memo)) ? $item->Memo : '';
                                            $input->reference = '';
                                        }

                                        //$input->url_referer = '';

                                        $input->status_code = SCART_STATUS_SCHEDULER_SCRAPE;
                                        $input->workuser_id = 0;
                                        $input->type_code = $type_code;
                                        $input->source_code = SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM;

                                        $input->received_at = date('Y-m-d H:i:s');

                                        $input->save();

                                        $input->generateFilenumber();
                                        $input->save();

                                        // log old/new for history
                                        $input->logHistory(SCART_INPUT_HISTORY_STATUS,'',SCART_STATUS_SCHEDULER_SCRAPE,"Import item from ICCAM");

                                        $input->logText("Added url '$item->Url' (reference is reportID=$lastID) by ICCAM import");

                                        $loglines[] = $status_timestamp."import url '$item->Url' (filenumber=$input->filenumber); type=$type_code (id=$siteTypeId)";


                                    } catch (\Exception $err) {

                                        $loglines[] = $status_timestamp."error inserting into SCART - SKIP - error message: " . $err->getMessage();
                                        scartLog::logLine("W-scartImportICCAM; error inserting into ERT, SKIP this one; message: " . $err->getMessage());

                                    }

                                } else {
                                    $loglines[] = $status_timestamp."import url '$item->Url' of ReportID '$lastID' already imported - SKIP";
                                    scartLog::logLine("D-scartImportICCAM; url '$item->Url' already imported into ERT");
                                }

                            }


                        } else {

                            $loglines[] = $status_timestamp."import reportID '$lastID' has already ACTIONS set in ICCAM ($txt) - SKIP";
                            scartLog::logLine("D-scartImportICCAM; reportID '$lastID' has already ACTIONS ($txt) set in ICCAM");

                        }



                    } else {
                        //$loglines[] = "reportID '$lastID' already imported into ERT";
                        scartLog::logLine("D-scartImportICCAM; got reportID=$lastID; already imported into ERT");
                    }

                    if (count($loglines) > 0) {

                        $reports[] = [
                            'reportID' => $lastID,
                            'loglines' => $loglines
                        ];

                    }

                }

                if (count($reports) > 0) {

                    // report JOB

                    $params = [
                        'reports' => $reports,
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_import_iccam', $params);

                }

                // wait for next one
                $maxID += 1;
                scartLog::logLine("D-scartImportICCAM; set last (max) ID on $maxID");
                self::saveImportLast(SCART_INTERFACE_ICCAM_ACTION_IMPORTLAST,$maxID);

            } else {

                if (scartICCAM::isOffline()) {
                    scartLog::logLine("W-scartImportICCAM; ICCAM OFFLINE!?");
                }

            }

        } else {
            scartLog::logLine("W-scartImportICCAM; cannot find last reportID !?");
        }

    }

    static function getImportlast($action) {

        $importjob = ImportExport_job::where('interface',SCART_INTERFACE_ICCAM)
            ->where('action',$action)
            ->first();
        return (($importjob) ? $importjob->data : '');
    }

    static function saveImportLast($action,$reportID) {

        $importjob = ImportExport_job::where('interface',SCART_INTERFACE_ICCAM)
            ->where('action',$action)
            ->first();
        if ($importjob) {
            if ($importjob->data != $reportID) {
                $importjob->data = $reportID;
                $importjob->save();
            }
        } else {
            $importjob = new ImportExport_job();
            $importjob->interface = SCART_INTERFACE_ICCAM;
            $importjob->action = $action;
            $importjob->data = $reportID;
            $importjob->save();
        }
    }


}
