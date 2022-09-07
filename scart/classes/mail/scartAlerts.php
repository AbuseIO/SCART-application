<?php
namespace abuseio\scart\classes\mail;

use Log;
use Lang;
use Config;
use abuseio\scart\models\Alert;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;

class scartAlerts {

    private $_lasterror = '';

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
        $alert->status_code = SCART_ALSCART_STATUS_CREATED;
        $alert->status_at = date('Y-m-d H:i:s');
        $alert->save();
        scartLog::logLine("D-insertAlert; alert inserted; level=$level, mailview=$mailview");
    }

    public static function timeForSend($level, $trigger_min) {

        if ($trigger_min >= 0) {
            // the time difference with the first created (level) alert
            $alertlast = Alert::where('level',$level)->where('status_code',SCART_ALSCART_STATUS_CREATED)->orderBy('status_at','asc')->first();
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
        scartLog::logLine("D-timeForSend; level=$level; ($mindiff >= $trigger_min) ");
        return ($mindiff >= $trigger_min);
    }

    /**
     * Group alerts (on mailview) and send in one time
     *
     * @param $level
     * @return mixed
     */

    public static function sendAlerts($level) {

        $alertcnt = Alert::where('status_code','=',SCART_ALSCART_STATUS_CREATED)->where('level',$level)->count();
        if ($alertcnt > 0) {
            $alertsgroup = [];
            $alerts = Alert::where('status_code','=',SCART_ALSCART_STATUS_CREATED)->where('level',$level)->get();
            foreach ($alerts AS $alert) {

                $mailview = '?';

                try {

                    scartLog::logLine("D-sendAlerts; id=$alert->id, mailview=$alert->mailview " );
                    $prm = @unserialize($alert->parameters);

                    if ($prm) {

                        //scartLog::logLine("D-prm: " . print_r($prm, true) );
                        $mailview = $alert->mailview;
                        if (!isset($alertsgroup[$mailview])) $alertsgroup[$mailview] = [];
                        switch ($mailview) {
                            case 'abuseio.scart::mail.scheduler_archive':
                            case 'abuseio.scart::mail.scheduler_update_whois':
                                if (!isset($alertsgroup[$mailview][0]['job_records'])) $alertsgroup[$mailview][0]['job_records'] = [];
                                foreach ($prm['job_records'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['job_records'][] = $prmjobs;
                                }
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['job_records']);
                                break;
                            case 'abuseio.scart::mail.scheduler_check_ntd':
                            case 'abuseio.scart::mail.scheduler_analyze_input':
                            case 'abuseio.scart::mail.scheduler_cleanup':
                                if (!isset($alertsgroup[$mailview][0]['job_inputs'])) $alertsgroup[$mailview][0]['job_inputs'] = [];
                                foreach ($prm['job_inputs'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['job_inputs'][] = $prmjobs;
                                }
                                if (isset($prm['scrapecleaned'])) {
                                    if (!isset($alertsgroup[$mailview][0]['scrapecleaned'])) $alertsgroup[$mailview][0]['scrapecleaned'] = 0;
                                    $alertsgroup[$mailview][0]['scrapecleaned'] += $prm['scrapecleaned'];
                                }
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['job_inputs']);
                                break;
                            case 'abuseio.scart::mail.scheduler_export_iccam':
                            case 'abuseio.scart::mail.scheduler_import_iccam':
                            case 'abuseio.scart::mail.scheduler_import_mailbox':
                                if (!isset($alertsgroup[$mailview][0]['reports'])) $alertsgroup[$mailview][0]['reports'] = [];
                                foreach ($prm['reports'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['reports'][] = $prmjobs;
                                }
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['reports']);
                                break;
                            case 'abuseio.scart::mail.scheduler_export_iccam_report_error':
                            case 'abuseio.scart::mail.scheduler_export_iccam_action_error':
                                if (!isset($alertsgroup[$mailview][0]['reports'])) $alertsgroup[$mailview][0]['reports'] = [];
                                $alertsgroup[$mailview][0]['reports'][] = $prm;
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['reports']);
                                break;
                            case 'abuseio.scart::mail.scheduler_ntd_send':
                                if (!isset($alertsgroup[$mailview][0]['ntd_nots'])) $alertsgroup[$mailview][0]['ntd_nots'] = [];
                                foreach ($prm['ntd_nots'] AS $prmjobs) {
                                    $alertsgroup[$mailview][0]['ntd_nots'][] = $prmjobs;
                                }
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['ntd_nots']);
                                break;
                            case 'abuseio.scart::mail.whois_new_abusecontact':
                            case 'abuseio.scart::mail.whois_set_abusecontact':
                            case 'abuseio.scart::mail.whois_changed':
                                if (!isset($alertsgroup[$mailview][0]['new_abuses'])) $alertsgroup[$mailview][0]['new_abuses'] = [];
                                $alertsgroup[$mailview][0]['new_abuses'][] = $prm;
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['new_abuses']);
                                break;
                            case 'abuseio.scart::mail.whois_notfound_abusecontact':
                                if (!isset($alertsgroup[$mailview][0]['not_founds'])) $alertsgroup[$mailview][0]['not_founds'] = [];
                                $alertsgroup[$mailview][0]['not_founds'][] = $prm;
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['not_founds']);
                                break;
                            case 'abuseio.scart::mail.whitelist_notfound_abusecontact':
                                if (!isset($alertsgroup[$mailview][0]['not_founds'])) $alertsgroup[$mailview][0]['not_founds'] = [];
                                $alertsgroup[$mailview][0]['not_founds'][] = $prm;
                                $alertsgroup[$mailview][0]['records_count'] = count($alertsgroup[$mailview][0]['not_founds']);
                                break;
                            default:
                                // each one
                                $alertsgroup[$mailview][] = $prm;
                                break;
                        }

                    }

                } catch (\Exception $err) {

                    scartLog::logLine("W-sendAlerts  error: mailview=$mailview; skip alert (id=$alert->id); line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

                }

                $alert->status_code = SCART_ALSCART_STATUS_SENT;
                $alert->save();
                //scartLog::logLine("D-Alert->id=$alert->id, mailview=$mailview, count(prm)=".count($prm) );
            }
            //scartLog::logLine("D-alertsgroup: " . print_r($alertsgroup, true) );
            foreach ($alertsgroup AS $mailview => $prms) {
                foreach ($prms AS $prm) {
                    self::sendAlert($level, $mailview, $prm );
                }
            }
        }
        return $alertcnt;
    }

    /**
     * sendAlert -> use mailview
     *
     * @param $mailview
     * @param array $mailprms
     */

    public static function sendAlert($alertlevel,$mailview,$mailprms=[]) {

        $level = Systemconfig::get('abuseio.scart::alerts.level');
        if ($alertlevel >= $level) {

            if ($alertlevel==SCART_ALERT_LEVEL_ADMIN) {
                $to = Systemconfig::get('abuseio.scart::alerts.admin_recipient');
            } else {
                $to = Systemconfig::get('abuseio.scart::alerts.recipient');
            }
            $bcc = Systemconfig::get('abuseio.scart::alerts.bcc_recipient');

            scartLog::logLine("D-sendAlert; send report '$mailview' to: $to");
            scartMail::sendMail($to, $mailview, $mailprms, $bcc);

        } else {
            scartLog::logLine("D-Skip sending alert (alertlevel=$alertlevel < $level) of mailview=$mailview");
        }

    }



}
