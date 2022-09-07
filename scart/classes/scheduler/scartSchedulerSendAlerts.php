<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\online\scartCheckOnline;
use Config;

use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;

class scartSchedulerSendAlerts extends scartScheduler {

    public static function doJob() {

        if (SELF::startScheduler('SendAlerts', 'sendalerts')) {

            try {

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

                /**
                 * Do some monitoring of the status of the system
                 *
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

                    $lastseen = scartSchedulerCheckOnline::Normal(0)
                        ->orderBy('lastseen_at','ASC')
                        ->select('lastseen_at')
                        ->first();
                    $lastseen = ($lastseen) ? $lastseen->lastseen_at : '';
                    $lastseenago = ($lastseen) ? (time() - strtotime($lastseen)) : '';
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
                        $look_again = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120);
                        $workloadcount = scartSchedulerCheckOnline::Normal($look_again)->count();

                        // check retry country for alerting -> alert after ($check_online_every) -> give classified records time to process
                        if (($sendalert % $check_online_every) == 0) {

                            // each half hour
                            scartLog::logLine("W-SEND realtime oldest warning; sendalert=$sendalert; ($lastseenago >= ($lookagain * 60); workloadcount=$workloadcount");

                            // (ONE TIME) send admin warning
                            $params = [
                                'reportname' => 'REALTIME OLDEST WARNING; lastseen not within allowed time!? ',
                                'report_lines' => [
                                    "Lastseen: $lastseen",
                                    "Lastseen ago (lookagain=$lookagain minutes): ".round($lastseenago / 60,0).' minutes',
                                    "Number of 'old' records: $workloadcount",
                                    "Alert count: $sendalert",
                                ]
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
                        } else {
                            scartLog::logLine("W-Realtime oldest warning; sendalert=$sendalert; ($lastseenago >= ($lookagain * 60); workloadcount=$workloadcount");
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

                    $old = date('Y-m-d H:i:s',strtotime("-12 hours"));
                    if ($cnt = scartCheckOnline::checkLocks($old)) {

                        // Send warning (1x)

                        $sendalert = scartUsers::getGeneralOption('REALTIME_OLD_CHECKONLINELOCK');
                        if (empty($sendalert)) $sendalert = 1;
                        $sendalert = intval($sendalert) + 1;

                        // check retry country for alerting -> alert after ($check_online_every) -> give classified records time to process
                        if (($sendalert % $check_online_every) == 0) {

                            // each half hour
                            scartLog::logLine("W-SEND realtime checkonline-lock old warning; sendalert=$sendalert; $cnt lock(s) older then $old");

                            // (ONE TIME) send admin warning
                            $params = [
                                'reportname' => 'REALTIME CHECKONLINE-LOCK OLD WARNING',
                                'report_lines' => [
                                    "Lock is older then : $old",
                                    "Number of records: $cnt",
                                    "Alert count: $sendalert",
                                ]
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
                        } else {
                            scartLog::logLine("W-Realtime checkonline-lock old warning; sendalert=$sendalert; $cnt lock(s) lock older then $old");
                        }
                        scartUsers::setGeneralOption('REALTIME_OLD_CHECKONLINELOCK', $sendalert);

                    } else {

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

                }

            }  catch (\Exception $err) {

                scartLog::logLine("E-SchedulerSendAlerts; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

        }

        SELF::endScheduler();

    }

}
