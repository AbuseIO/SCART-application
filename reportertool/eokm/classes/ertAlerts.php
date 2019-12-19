<?php
namespace reportertool\eokm\classes;

use League\Flysystem\Exception;
use Log;
use Config;
use ReporterTool\EOKM\Models\Alert;

class ertAlerts {

    /**
     *
     * @param $level
     * @param $mailview
     * @param $parameters
     */

    public static function insertAlert($level, $mailview, $parameters) {

        $prms = serialize($parameters);
        $alert = new Alert();
        $alert->level = $level;
        $alert->mailview = $mailview;
        $alert->parameters = $prms;
        $alert->status_code = ERT_ALERT_STATUS_CREATED;
        $alert->status_at = date('Y-m-d H:i:s');
        $alert->save();
        ertLog::logLine("D-insertAlert; alert inserted; level=$level, mailview=$mailview");
    }

    public static function timeForSend($level, $trigger_min) {

        if ($trigger_min != 0) {
            // the time difference with the first created (level) alert
            $alertlast = Alert::where('level',$level)->where('status_code',ERT_ALERT_STATUS_CREATED)->orderBy('status_at','asc')->first();
            if ($alertlast) {
                $mindiff = round(((time() - strtotime($alertlast->status_at) ) / 60),1);
            } else {
                // do nothing
                $mindiff = -1;
            }
        } else {
            // = 0; do nothing
            $mindiff = -1;
        }
        ertLog::logLine("D-timeForSend; level=$level; ($mindiff >= $trigger_min) ");
        return ($mindiff >= $trigger_min);
    }

    /**
     * Group alerts (on mailview) and send in one time
     *
     * @param $level
     * @return mixed
     */

    public static function sendAlerts($level) {

        $alertcnt = Alert::where('status_code','=',ERT_ALERT_STATUS_CREATED)->where('level',$level)->count();
        if ($alertcnt > 0) {
            $alertsgroup = [];
            $alerts = Alert::where('status_code','=',ERT_ALERT_STATUS_CREATED)->where('level',$level)->get();
            foreach ($alerts AS $alert) {

                try {

                    //ertLog::logLine("D-sendAlerts; id=$alert->id " );
                    $prm = @unserialize($alert->parameters);

                    if ($prm) {

                        //ertLog::logLine("D-prm: " . print_r($prm, true) );
                        $mailview = $alert->mailview;
                        if (!isset($alertsgroup[$mailview])) $alertsgroup[$mailview] = [];
                        switch ($mailview) {
                            case 'reportertool.eokm::mail.scheduler_cleanup':
                                if (!isset($alertsgroup[$mailview][0]['job_inputs'])) $alertsgroup[$mailview][0]['job_inputs'] = [];
                                foreach ($prm['job_inputs'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['job_inputs'][] = $prmjobs;
                                }
                                if (!isset($alertsgroup[$mailview][0]['scrapecleaned'])) $alertsgroup[$mailview][0]['scrapecleaned'] = 0;
                                $alertsgroup[$mailview][0]['scrapecleaned'] += $prm['scrapecleaned'];
                                break;
                            case 'reportertool.eokm::mail.scheduler_analyze_input':
                                if (!isset($alertsgroup[$mailview][0]['job_inputs'])) $alertsgroup[$mailview][0]['job_inputs'] = [];
                                foreach ($prm['job_inputs'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['job_inputs'][] = $prmjobs;
                                }
                                if (!isset($alertsgroup[$mailview][0]['job_nots'])) $alertsgroup[$mailview][0]['job_nots'] = [];
                                foreach ($prm['job_nots'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['job_nots'][] = $prmjobs;
                                }
                                break;
                            case 'reportertool.eokm::mail.scheduler_check_ntd':
                                if (!isset($alertsgroup[$mailview][0]['job_inputs'])) $alertsgroup[$mailview][0]['job_inputs'] = [];
                                foreach ($prm['job_inputs'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['job_inputs'][] = $prmjobs;
                                }
                                break;
                            case 'reportertool.eokm::mail.scheduler_import_iccam':
                            case 'reportertool.eokm::mail.scheduler_export_iccam':
                            case 'reportertool.eokm::mail.scheduler_import_mailbox':
                                if (!isset($alertsgroup[$mailview][0]['reports'])) $alertsgroup[$mailview][0]['reports'] = [];
                                foreach ($prm['reports'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['reports'][] = $prmjobs;
                                }
                                break;
                            case 'reportertool.eokm::mail.scheduler_ntd_failed_send':
                                if (!isset($alertsgroup[$mailview][0]['ntd_nots'])) $alertsgroup[$mailview][0]['ntd_nots'] = [];
                                foreach ($prm['ntd_nots'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['ntd_nots'][] = $prmjobs;
                                }
                                break;
                            case 'reportertool.eokm::mail.whois_new_abusecontact':
                                if (!isset($alertsgroup[$mailview][0]['new_abuses'])) $alertsgroup[$mailview][0]['new_abuses'] = [];
                                $alertsgroup[$mailview][0]['new_abuses'][] = $prm;
                                break;
                            default:
                                $alertsgroup[$mailview][] = $prm;
                                break;
                        }

                    }

                } catch (Exception $err) {

                    SELF::$_lasterror = $err->getMessage();
                    ertLog::logLine("W-sendAlerts  error: skip alert (id=$alert->id); line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );

                }

                $alert->status_code = ERT_ALERT_STATUS_SENT;
                $alert->save();
                //ertLog::logLine("D-Alert->id=$alert->id, mailview=$mailview, count(prm)=".count($prm) );
            }
            //ertLog::logLine("D-alertsgroup: " . print_r($alertsgroup, true) );
            foreach ($alertsgroup AS $mailview => $prms) {
                foreach ($prms AS $prm) {
                    ertMail::sendAlert($level, $mailview, $prm);
                }
            }
        }
        return $alertcnt;
    }
}
