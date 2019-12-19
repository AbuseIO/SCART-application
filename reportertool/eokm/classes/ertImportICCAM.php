<?php
namespace reportertool\eokm\classes;

use Config;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\ImportExport_job;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Plugin;

class ertImportICCAM {

    public static function doImport($scheduler_process_count) {

        try {

            if (Config::get('reportertool.eokm::iccam.active', false)) {

                if ( !($lastID = self::getImportlast()) ) {
                    $lastID = ertICCAM2ERT::readICCAMlastReportID();
                    self::saveImportLast($lastID);
                }

                if ($lastID) {

                    // get reports since last reportID
                    $iccamreports = ertICCAM2ERT::readICCAMfrom([
                        'startID' => $lastID,
                        'status' => ERT_ICCAM_REPORTSTATUS_EITHER,
                        'origin' => ERT_ICCAM_REPORTORIGIN_USERCOUNTRY,
                    ]);
                    if ($iccamreports && (count($iccamreports) > 0) ) {

                        $reports = [];

                        foreach ($iccamreports as $iccamreport) {

                            $loglines = [];

                            $lastID = $iccamreport->ReportID;
                            $note = (!empty($iccamreport->Memo)) ? $iccamreport->Memo : '';

                            // check if not already in ERT
                            if (!ertICCAM2ERT::alreadyImportedICCAMreportID($lastID)) {

                                if (!ertICCAM2ERT::alreadyActionsSetICCAMreportID($lastID)) {

                                    // check if siteType is set -> convert to ERT siteType value
                                    $siteTypeId = (isset($iccamreport->SiteTypeID)) ? $iccamreport->SiteTypeID : 1;
                                    $siteTypeId = (is_numeric($siteTypeId)) ? intval($siteTypeId) : 1;
                                    $key = array_search($siteTypeId,ertICCAM2ERT::$siteTypeMap);
                                    $type_code = ($key!==false) ? $key : ERT_ICCAM_IMPORT_TYPE_CODE_ICCAM;

                                    $first = true;
                                    foreach ($iccamreport->Items AS $item) {

                                        ertLog::logLine("D-ertImportICCAM; found url '$item->Url' to import ");

                                        if (Input::where('url',$item->Url)->count() == 0) {

                                            try {

                                                $input = new Input();
                                                $input->url = $item->Url;

                                                // only the first get reportID -> import other items with reference = empty
                                                // -> then this will lead to an new export to ICCAM with new reportID

                                                if ($first) {
                                                    $input->note = $note;
                                                    $input->reference = ertICCAM2ERT::setICCAMreportID($lastID);
                                                    $first = false;  // reset
                                                } else {
                                                    $input->note = (!empty($item->Memo)) ? $item->Memo : '';
                                                    $input->reference = '';
                                                }

                                                //$input->url_referer = '';

                                                $input->status_code = ERT_STATUS_SCHEDULER_SCRAPE;
                                                $input->workuser_id = 0;
                                                $input->type_code = $type_code;
                                                $input->source_code = ERT_ICCAM_IMPORT_SOURCE_CODE_ICCAM;

                                                $input->received_at = date('Y-m-d H:i:s');

                                                $input->save();

                                                $input->generateFilenumber();
                                                $input->save();

                                                $input->logText("Added url '$item->Url' (reference is reportID=$lastID) by ICCAM import");

                                                $loglines[] = "import url '$item->Url' (filenumber=$input->filenumber); type=$type_code (id=$siteTypeId)";


                                            } catch (\Exception $err) {

                                                $loglines[] = "error inserting into ERT - SKIP - error message: " . $err->getMessage();
                                                ertLog::logLine("W-ertImportICCAM; error inserting into ERT, SKIP this one; message: " . $err->getMessage());

                                            }

                                        } else {
                                            $loglines[] = "import url '$item->Url' of ReportID '$lastID' already imported - SKIP";
                                            ertLog::logLine("D-ertImportICCAM; url '$item->Url' already imported into ERT");
                                        }

                                    }


                                } else {
                                    $loglines[] = "import reportID '$lastID' has already ACTIONS set in ICCAM - SKIP";
                                    ertLog::logLine("D-ertImportICCAM; reportID '$lastID' has already ACTIONS set in ICCAM");

                                }



                            } else {
                                //$loglines[] = "reportID '$lastID' already imported into ERT";
                                ertLog::logLine("D-ertImportICCAM; got reportID=$lastID; already imported into ERT");
                            }

                            if (count($loglines) > 0) {

                                $reports[] = [
                                    'reportID' => $lastID,
                                    'loglines' => $loglines
                                ];

                                if (count($loglines) > $scheduler_process_count) {
                                    break;
                                }

                            }

                        }

                        if (count($reports) > 0) {

                            // report JOB

                            $cnt = 0;

                            $params = [
                                'reports' => $reports,
                            ];
                            ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.scheduler_import_iccam', $params);

                        }

                        // wait for next one
                        self::saveImportLast(intval($lastID) + 1);

                    }

                } else {
                    ertLog::logLine("W-ertImportICCAM; cannot find last reportID !?");
                }

            }

        } catch (\Exception $err) {

            ertLog::logLine("E-ertImportICCAM; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

    }

    static function getImportlast() {

        $importjob = ImportExport_job::where('interface',ERT_INTERFACE_ICCAM)
            ->where('action',ERT_INTERFACE_ICCAM_ACTION_IMPORTLAST)
            ->first();
        return (($importjob) ? $importjob->data : '');
    }

    static function saveImportLast($reportID) {

        $importjob = ImportExport_job::where('interface',ERT_INTERFACE_ICCAM)
            ->where('action',ERT_INTERFACE_ICCAM_ACTION_IMPORTLAST)
            ->first();
        if ($importjob) {
            if ($importjob->data != $reportID) {
                $importjob->data = $reportID;
                $importjob->save();
            }
        } else {
            $importjob = new ImportExport_job();
            $importjob->interface = ERT_INTERFACE_ICCAM;
            $importjob->action = ERT_INTERFACE_ICCAM_ACTION_IMPORTLAST;
            $importjob->data = $reportID;
            $importjob->save();
        }
    }


}
