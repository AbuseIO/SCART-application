<?php
namespace reportertool\eokm\classes;

use Config;
use ReporterTool\EOKM\Models\ImportExport_job;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;

class ertExportICCAM {

    public static function doExport() {

        try {

            if (Config::get('reportertool.eokm::iccam.active', false)) {

                $reports = $loglines = [];
                $ICCAMreportID = '?';

                // 1-by-1; ICCAM does not like all at once

                $job = self::getOneExportAction();

                if ($job) {

                    switch ($job['action']) {

                        case ERT_INTERFACE_ICCAM_ACTION_EXPORTREPORT:

                            // validate & get record input
                            if ($record = self::getDataRecord($job['data']) ) {
                                if ($record->reference == '' ) {
                                    $ICCAMreportID = ertICCAM2ERT::insertERT2ICCAM($record);
                                    if ($ICCAMreportID) {
                                        $record->reference = ertICCAM2ERT::setICCAMreportID($ICCAMreportID);
                                        $record->logText("(ICCAM) Exported; got ICCAM reportID=$ICCAMreportID");
                                    }
                                    $record->save();
                                    $loglines[] = "export ERT report to ICCAM";
                                } else {
                                    ertLog::logLine("W-ICCAM reference already set ");
                                }
                            } else {
                                ertLog::logLine("W-ICCAM no valid exportdata: " . print_r(jobs, true) );
                            }
                            break;

                        case ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION:

                            // validate & get record input
                            if ($record = self::getDataRecord($job['data']) ) {
                                if ($record->reference != '' ) {
                                    // ICCAM set ERT_ICCAM_ACTION_LEA
                                    $ICCAMreportID = ertICCAM2ERT::getICCAMreportID($record->reference);
                                    // note: we have also object_id
                                    $actionID = $job['data']['action_id'];
                                    $county = $job['data']['country'];
                                    $reason = $job['data']['reason'];
                                    ertICCAM2ERT::insertERTaction2ICCAM($ICCAMreportID,$actionID,$record->workuser_id,$county,$reason);
                                    $record->logText("(ICCAM) $reason");
                                    $loglines[] = "export action (".ertICCAM2ERT::$actionMap[$actionID].") report to ICCAM";
                                } else {
                                    ertLog::logLine("W-doCheckOnline; empty ICCAMreportID; id=$record->id cannot export action ERT_ICCAM_ACTION_LEA");
                                }
                            } else {
                                ertLog::logLine("W-ICCAM no valid exportdata: " . print_r(jobs, true) );
                            }
                            break;

                    }

                    self::delExportAction($job);

                    if (count($loglines) > 0) {
                        $reports[] = [
                            'reportID' => $ICCAMreportID,
                            'loglines' => $loglines,
                        ];
                    }


                    // one-time -> report

                    if (count($reports) > 0) {

                        // report JOB

                        $params = [
                            'reports' => $reports,
                        ];
                        ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.scheduler_export_iccam', $params);

                    }


                }

            }

        } catch (\Exception $err) {

            ertLog::logLine("E-exportICCAM exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

    }

    static function getDataRecord($jobdata) {

        if (isset($jobdata['record_type']) && isset($jobdata['record_id']) ) {
            $record = (strtolower($jobdata['record_type'])==ERT_INPUT_TYPE) ? Input::find($jobdata['record_id']) : Notification::find($jobdata['record_id']);
        } else {
            $reocrd = '';
        }
        return $record;
    }

    /**
     * Add Export Action to importexport jobs
     *
     * Note: check if action already done for reportID
     *
     * @param $action
     * @param $data
     */
    public static function addExportAction($action,$data) {

        // unique checksum each record -> onetime action for reportID
        $checksum = $data['record_type'].'-'.$data['record_id'].(isset($data['action_id'])? '-'.$data['action_id'] : '');

        // check if not already, including trash
        $cnt = ImportExport_job::where('interface',ERT_INTERFACE_ICCAM)
            ->where('action',$action)
            ->where('checksum',$checksum)
            ->withTrashed()
            ->count();

        if ($cnt == 0) {
            // create if
            $export = new ImportExport_job();
            $export->interface = ERT_INTERFACE_ICCAM;
            $export->action = $action;
            $export->checksum = $checksum;
            $export->data = serialize($data);
            $export->save();
        }
    }

    static function getOneExportAction() {

        $export = ImportExport_job::where('interface',ERT_INTERFACE_ICCAM)
            ->whereIn('action',[ERT_INTERFACE_ICCAM_ACTION_EXPORTREPORT,ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION])
            ->orderBy('id','asc')
            ->first();
        if ($export) {
            $arrdata = explode('#$#',$export->data);
            $job = [
                'job_id' => $export->id,
                'action' => $export->action,
                'data' => unserialize($export->data),
            ];
        } else {
            $job = '';
        }
        return $job;
    }

    static function delExportAction($job) {
        ImportExport_job::find($job['job_id'])->delete();
    }



}
