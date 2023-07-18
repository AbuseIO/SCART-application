<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\online\scartCheckOnline;
use abuseio\scart\Models\Maintenance;
use Config;

use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;

/**
 * SendAlerts
 *
 * General scheduler for alerts and monitor purposes
 *
 */

class scartSchedulerSendAlerts extends scartScheduler {

    public static function doJob() {

        if (SELF::startScheduler('SendAlerts', 'sendalerts')) {

            try {

                // Check if there are alerts to be send

                $send_alerts_info  = Systemconfig::get('abuseio.scart::scheduler.sendalerts.info', 15);
                if (scartAlerts::timeForSend(SCART_ALERT_LEVEL_INFO, $send_alerts_info) ) {
                    scartAlerts::sendAlerts(SCART_ALERT_LEVEL_INFO);
                }

                $send_alerts_warning  = Systemconfig::get('abuseio.scart::scheduler.sendalerts.warning', 1);
                if (scartAlerts::timeForSend(SCART_ALERT_LEVEL_WARNING, $send_alerts_warning) ) {
                    scartAlerts::sendAlerts(SCART_ALERT_LEVEL_WARNING);
                }

                $send_alerts_admin  = Systemconfig::get('abuseio.scart::scheduler.sendalerts.admin', 1);
                if (scartAlerts::timeForSend(SCART_ALERT_LEVEL_ADMIN, $send_alerts_admin) ) {
                    scartAlerts::sendAlerts(SCART_ALERT_LEVEL_ADMIN);
                }

                // Monitoring the status of the system

                $iccammaintenance = Maintenance::checkICCAMdisabled();
                if ($iccammaintenance!==null) {
                    if ($iccammaintenance) {
                        scartLog::logLine("D-schedulerSendAlerts; ICCAM maintenance; set ICCAM active on false");
                        Systemconfig::set('abuseio.scart::scheduler.importexport.iccam_active',false);
                    } else {
                        scartLog::logLine("D-schedulerSendAlerts; ICCAM maintenance; set ICCAM active on true");
                        Systemconfig::set('abuseio.scart::scheduler.importexport.iccam_active',true);
                    }
                }

                /**
                 * - check if realtime workers are not behide workload
                 * - check if no (long) outstanding checkonline-locks
                 *
                 */

                // realtime -> check if workers are handling the load
                $mode = Systemconfig::get('abuseio.scart::scheduler.checkntd.mode',SCART_CHECKNTD_MODE_CRON);
                $moderealtime = ($mode == SCART_CHECKNTD_MODE_REALTIME);
                if ($moderealtime) {

                    scartLog::logLine("D-schedulerSendAlerts; check if realtime checkonline is running okay");

                    // -1- behide workload

                    $lastseen = scartSchedulerCheckOnline::lastseen();
                    $lastseen = ($lastseen) ? $lastseen->lastseen_at : '';
                    $lastseenago = ($lastseen) ? (time() - strtotime($lastseen)) : 0;
                    // note: CRON then more then 24 hours as warning
                    $lookagain = ($moderealtime) ? Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120) : (24 * 60);
                    $check_online_every = Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',15);

                    $warning = ($lastseenago >= ($lookagain * 60));
                    if ($warning) {

                        // Send warning (1x)

                        $sendalert = scartUsers::getGeneralOption('REALTIME_OLDEST_WARNING');
                        if (empty($sendalert)) $sendalert = 1;
                        $sendalert = intval($sendalert) + 1;

                        // records with more then (look_again) minutes last check
                        $lookagaintime = date('Y-m-d H:i:s', strtotime("-$lookagain minutes"));
                        $lastseencnt = scartSchedulerCheckOnline::lastseenCount($lookagaintime);

                        // check retry country for alerting -> alert after ($check_online_every) -> give classified records time to process
                        if (($sendalert % $check_online_every) == 0) {

                            // each half hour
                            scartLog::logLine("W-SEND realtime oldest warning; sendalert=$sendalert; ($lastseenago >= ($lookagain * 60); lastseencnt=$lastseencnt");

                            $report_lines = [
                                "Lastseen normal/firsttime record: $lastseen",
                                'Current max time: '.$lookagaintime,
                                "Lastseen ago (lookagain=$lookagain minutes): ".round($lastseenago / 60,0).' minutes',
                                "Number of 'old' normal/firsttime records: $lastseencnt",
                                "Alert count: $sendalert",
                                "Top 10 'old' normal/firsttime records:",
                            ];

                            $oldrecords = scartSchedulerCheckOnline::lastseenTop10($lookagaintime);
                            foreach ($oldrecords AS $oldrecord) {
                                $type = 'Normal';
                                if ($oldrecord->online_counter == 0) {
                                    $type = 'FirstTime';
                                }
                                $report_lines[] = "_filenumber=$oldrecord->filenumber, status=$oldrecord->status_code, type=$type, lastseen=$oldrecord->lastseen_at";
                            }

                            // (ONE TIME) send admin warning
                            $params = [
                                'reportname' => 'REALTIME OLDEST WARNING; lastseen not within allowed time ',
                                'report_lines' => $report_lines
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
                        } else {
                            scartLog::logLine("W-Realtime oldest warning; no time to (re)send; ($sendalert % $check_online_every) <> 0; ($lastseenago >= ($lookagain * 60); lastseencnt=$lastseencnt ");
                        }
                        scartUsers::setGeneralOption('REALTIME_OLDEST_WARNING', $sendalert);

                    } else {

                        $sendalert = scartUsers::getGeneralOption('REALTIME_OLDEST_WARNING');

                        if ($sendalert >= $check_online_every) {

                            scartLog::logLine("D-Sent alert REALTIME_OLDEST_WARNING is over");

                            // (ONE TIME) send admin warning over
                            $params = [
                                'reportname' => 'REALTIME OLDEST WITHIN allowed time again ',
                                'report_lines' => [
                                    "Lastseen: $lastseen",
                                    "Lastseen ago (lookagain=$lookagain minutes): ".round($lastseenago / 60,0).' minutes',
                                ]
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                            scartUsers::setGeneralOption('REALTIME_OLDEST_WARNING', '');

                        }

                    }

                    // -2- checkonline-lock old

                    $old = date('Y-m-d H:i:s',strtotime("-24 hours"));
                    if ($cnt = scartCheckOnline::checkLocks($old)) {

                        // Log lock(s) and RESET old lock(s)

                        $sendalert = scartUsers::getGeneralOption('REALTIME_OLD_CHECKONLINELOCK');
                        if (empty($sendalert)) $sendalert = 1;
                        $sendalert = intval($sendalert) + 1;

                        if (($sendalert % $check_online_every) == 0) {

                            scartLog::logLine("W-SEND alert realtime checkonline-lock old warning; $cnt lock(s) older then $old");

                            // get which type is locked
                            $oldlockrecords = scartCheckOnline::getOldLocks($old);
                            $normal = $retry = $firsttime = 0;
                            foreach ($oldlockrecords AS $oldlockrecord) {
                                if ($oldlockrecord->online_counter == 0) {
                                    $firsttime += 1;
                                } elseif ($oldlockrecord->browse_error_retry == 0) {
                                    $normal += 1;
                                } else {
                                    $retry += 1;
                                }
                                //$report_lines[] = $oldlockrecord->filenumber.', status='.$oldlockrecord->status_code.', browse_error_retry='.$oldlockrecord->browse_error_retry.', checkonline_lock='.$oldlockrecord->checkonline_lock." <br />\n";
                            }

                            $report_lines = [
                                "Lock(s) older then : $old",
                                "Number of locked records: $cnt",
                                "Number of FIRSTTIME locked: $firsttime ",
                                "Number of RETRY locked: $retry ",
                                "Number of NORMAL locked: $normal ",
                            ];

                            // Send admin warning and lock filenumber(s)
                            $params = [
                                'reportname' => 'REALTIME OLD LOCKS CHECKONLINE',
                                'report_lines' => $report_lines,
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                        } else {
                            scartLog::logLine("W-Realtime checkonline-lock old warning; $cnt lock(s) older then $old; no alert send ($sendalert % $check_online_every) <> 0");
                        }

                        //scartCheckOnline::resetOldLocks($old);

                        scartUsers::setGeneralOption('REALTIME_OLD_CHECKONLINELOCK', $sendalert);

                    }

                    /*
                    else {

                        $sendalert = scartUsers::getGeneralOption('REALTIME_OLD_CHECKONLINELOCK');

                        if ($sendalert >= $check_online_every) {

                            scartLog::logLine("D-Sent alert checkonline-lock old is over");

                            // (ONE TIME) send admin warning over
                            $params = [
                                'reportname' => 'REALTIME CHECKONLINE-LOCK NOT TO OLD ANYMORE',
                                'report_lines' => [
                                    "No lock older then : $old",
                                ]
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                            scartUsers::setGeneralOption('REALTIME_OLD_CHECKONLINELOCK', '');

                        }

                    }
                    */

                }

            }  catch (\Exception $err) {

                scartLog::logLine("E-SchedulerSendAlerts; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

        }

        SELF::endScheduler();

    }

}
