<?php
namespace abuseio\scart\classes\iccam\api2;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\classes\iccam\api2\scartICCAMfields;
use Config;
use abuseio\scart\Controllers\Inputs_import;
use abuseio\scart\models\ImportExport_job;
use abuseio\scart\models\Input;
use abuseio\scart\models\Notification;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\iccam\api2\scartICCAM;
use abuseio\scart\classes\iccam\api2\scartICCAMmapping;

class scartExportICCAM {

    public  function do() {

        try {

            $export_iccam_eachtime =  Systemconfig::get('abuseio.scart::iccam.exportmax',20);

            $reports = [];

            $notinonerun = [];

            $jobs = scartICCAMinterface::getExportActions($export_iccam_eachtime);
            scartLog::logLine("D-scartExportICCAM; export jobs count: " . count($jobs) );
            $iccamwaitfornext = 1;  // ICCAM wait for next API call

            foreach ($jobs AS $job) {

                scartLog::logLine("D-scartExportICCAM; got job-id: ". $job['job_id'] .", action: " . $job['action'] . ", timestamp: " . $job['timestamp']);

                $ICCAMreportID = '?';
                $loglines = [];
                $status_timestamp = '['.date('Y-m-d H:i:s').'] ';
                $skip = false;

                $status = SCART_IMPORTEXPORT_STATUS_SUCCESS; $status_text = '';

                switch ($job['action']) {

                    case SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT:

                        // validate & get record input
                        if ($record = self::getDataRecord($job['data']) ) {

                            // Do insert or update in ICCAM

                            if ($record->reference == '') {
                                $logline = "url: $record->url ($record->filenumber)";
                            } else {
                                $logline = $status_text = "ICCAM reference already set (reference=$record->reference) - update in ICCAM";
                            }

                            scartLog::logLine("D-scartExportICCAM; $logline");
                            $loglines[] = $status_timestamp.$logline;

                            $ICCAMreportID = scartICCAMmapping::insertUpdateICCAM($record);
                            if ($ICCAMreportID && is_numeric($ICCAMreportID)) {

                                if ($record->reference == '') {
                                    $record->logHistory(SCART_INPUT_HISTORY_ICCAM,'',$ICCAMreportID,'Got reportID from export to ICCAM');
                                    // if empty (new), then actions not in same run
                                    $notinonerun[] = $ICCAMreportID;
                                    $record->logText("(ICCAM) Exported; got ICCAM reportID=$ICCAMreportID");
                                }

                                $record->reference = scartICCAMinterface::setICCAMreportID($ICCAMreportID);
                                $record->save();

                                $loglines[] = $status_timestamp."exported (insert) SCART report to ICCAM";

                            } else {

                                $ICCAMreportID = '?';

                                // 2020/6/9/gs: if ICCAM offline then skip
                                if (scartICCAM::isOffline()) {
                                    $logline = $status_text = "ICCAM OFFLINE? - CANNOT export to ICCAM - SKIP and RETRY later";
                                    $loglines[] = $status_timestamp.$logline;
                                    scartLog::logLine("W-scartExportICCAM; $logline");
                                    $status = SCART_IMPORTEXPORT_STATUS_EXPORT;
                                    $skip = true;
                                } elseif ($record->reference == '') {
                                    $logline = $status_text = "CAN NOT export to ICCAM " . (($ICCAMreportID) ? $ICCAMreportID : ' - already in ICCAM!?');
                                    $loglines[] = $status_timestamp.$logline;
                                    scartLog::logLine("W-scartExportICCAM; $logline");
                                    $status = SCART_IMPORTEXPORT_STATUS_ERROR;
                                    $status_text = "[filenumber=$record->filenumber] ".$logline;
                                } else {
                                    // update iccam; result=empty?!
                                    $loglines[] = $status_timestamp."exported (update) SCART report to ICCAM ";
                                    $record->logText("(ICCAM) Exported; updated ICCAM");
                                    // get current ICCAM reportID
                                    $ICCAMreportID = scartICCAMinterface::getICCAMreportID($record->reference);
                                }

                            }

                        } else {
                            $logline = $status_text = "ICCAM no valid (technical) exportdata";
                            $loglines[] = $status_timestamp.$logline;
                            scartLog::logLine("W-scartExportICCAM; $status_text; job=" . print_r($job, true) );
                            $status = SCART_IMPORTEXPORT_STATUS_ERROR;
                        }
                        break;

                    case SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION:

                        // validate & get record input
                        if ($record = self::getDataRecord($job['data']) ) {
                            $loglines[] = "url: $record->url ($record->filenumber)";
                            $actionID = $job['data']['action_id'];
                            if ($ICCAMreportID = scartICCAMinterface::getICCAMreportID($record->reference)) {
                                // ICCAM set action
                                if (!in_array($ICCAMreportID, $notinonerun)) {
                                    $county = $job['data']['country'];
                                    $reason = $job['data']['reason'];
                                    if ($warning = scartICCAMmapping::insertERTaction2ICCAM($ICCAMreportID,$actionID,$record,$county,$reason)) {

                                        if (scartICCAM::isOffline()) {
                                            $logline = $status_text = "ICCAM OFFLINE? - error export action (".scartICCAMfields::$actionMap[$actionID].") report to ICCAM - skip and retry later";
                                            $loglines[] = $status_timestamp.$logline;
                                            scartLog::logLine("W-scartExportICCAM; $logline");
                                            $status = SCART_IMPORTEXPORT_STATUS_EXPORT;
                                            $skip = true;
                                        } else {

                                            $logline = $status_text = "error export action (".scartICCAMfields::$actionMap[$actionID].") report to ICCAM ";
                                            $loglines[] = $status_timestamp.$logline;
                                            scartLog::logLine("W-scartExportICCAM; $logline");

                                            // Read action already set in ICCAM -> report

                                            $currentactions = scartICCAMmapping::alreadyActionsSetICCAMreportID($ICCAMreportID,true);
                                            if ($currentactions) {
                                                $logline = "found the following action(s): $currentactions";
                                            } else {
                                                $logline = "found NO other action(s) in ICCAM - may be report in ICCAM closed or updates disabled? ";
                                            }
                                            $loglines[] = $status_timestamp.$logline;
                                            scartLog::logLine("W-scartExportICCAM; $logline");

                                            $status = SCART_IMPORTEXPORT_STATUS_ERROR;
                                            $status_text .= "; $logline";
                                        }

                                    } else {
                                        $record->logText("(ICCAM) $reason");
                                        $logtext = "Export action '".scartICCAMfields::$actionMap[$actionID]."' with reason '$reason' to ICCAM ";
                                        $loglines[] = $status_timestamp.$logtext;

                                        // ICCAMreportID no change, force history insert, external (iccam) action add
                                        $record->logHistory(SCART_INPUT_HISTORY_ICCAM,$ICCAMreportID,$ICCAMreportID,$logtext,true);

                                    }

                                } else {
                                    $skip = true;
                                    $status = SCART_IMPORTEXPORT_STATUS_EXPORT;
                                    scartLog::logLine("W-scartExportICCAM; skip this action ($actionID) from ReportId '$ICCAMreportID' - cannot in same ICCAM API run ");
                                }
                            } else {
                                $logline = $status_text = "empty (ICCAM) reportID in SCART!?; cannot export action " . scartICCAMfields::$actionMap[$actionID];
                                scartLog::logLine("W-scartExportICCAM; $logline");
                                $loglines[] = $status_timestamp.$logline;
                                $status = SCART_IMPORTEXPORT_STATUS_ERROR;
                                $ICCAMreportID = '?';
                            }
                        } else {
                            $logline = $status_text = "ICCAM no valid exportdata";
                            scartLog::logLine("W-scartExportICCAM; $logline: " . print_r($jobs, true) );
                            $loglines[] = $status_timestamp.$logline;
                            $status = SCART_IMPORTEXPORT_STATUS_ERROR;
                        }
                        break;

                }

                if (!$skip) {

                    // UPDATE abuseio_scart_importexport_job with status and status_text

                    $importexport = ImportExport_job::withTrashed()->find($job['job_id']);
                    if ($importexport) {
                        $importexport->status = $status;
                        $importexport->status_text = $status_text;
                        $importexport->save();
                    }

                    // Note: soft delete
                    scartICCAMinterface::delExportAction($job);

                }
                if (count($loglines) > 0) {
                    $reports[] = [
                        'reportID' => $ICCAMreportID,
                        'loglines' => $loglines,
                    ];
                }

                // sleep to overcome ICCAM API overloading
                sleep($iccamwaitfornext);
            }

            if (count($reports) > 0) {

                // report JOB
                $params = [
                    'reports' => $reports,
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_export_iccam', $params);
            }
        } catch (\Exception $err) {

            scartLog::logLine("E-scartExportICCAM; error exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

    }

    static function getDataRecord($jobdata) {

        if (isset($jobdata['record_type']) && isset($jobdata['record_id']) ) {
            $record = (strtolower($jobdata['record_type'])==SCART_INPUT_TYPE) ? Input::find($jobdata['record_id']) : Notification::find($jobdata['record_id']);
        } else {
            $record = '';
        }
        return $record;
    }



}
