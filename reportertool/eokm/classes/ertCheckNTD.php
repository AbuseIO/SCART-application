<?php
namespace reportertool\eokm\classes;

use Config;
use reportertool\eokm\classes\ertICCAM2ERT;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Ntd;
use reportertool\eokm\classes\ertAnalyzeInput;
use ReporterTool\EOKM\Models\Whois;

class ertCheckNTD {

    /**
     * Checkonline
     *
     * Comes here when:
     * a. first time
     * b. lastseen (last check) is to long ago
     *
     * Note:
     * - may be optimize when input get_images also get image from illegal linked image
     * - only when input is also marked as illegal
     *
     * @param $input
     * @param $check_online_every
     * @param $registrar_interval
     * @return array
     */

    public static function doCheckIllegalOnline($record,$check_online_every,$registrar_interval) {

        $job_records = [];

        // check if first time (online_counter=0)

        if ($record->online_counter==0) {

            // FIRST TIME always init NTD (context)
            ertLog::logLine("D-doCheckOnline; first time for illegal content (id=$record->id); (record_type=".class_basename($record).") setup NTD for status_code=$record->status_code");

            // ICCAM -> export ERT report if not already done
            if ($record->reference == '' && (ertICCAM2ERT::isActive()) ) {
                ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTREPORT,[
                    'record_type' => class_basename($record),
                    'record_id' => $record->id,
                ]);
            }

            // verify WhoIs (always -> can be changed BY RULES!?)
            $whois = ertWhois::verifyWhoIs($record, false);

            // get abusecontact
            $abusecontact = Abusecontact::find($record->host_abusecontact_id);

            if ($whois['status_success'] && $abusecontact) {

                // create one DIRECTLY -> sent direct to abusecontact

                if ($record->status_code==ERT_STATUS_FIRST_POLICE) {

                    // find police contact
                    $abusecontact_id =  Abusecontact::findPolice();

                    if ($abusecontact_id) {
                        $ntdstat = ERT_NTD_STATUS_QUEUE_DIRECTLY_POLICE;
                        ertLog::logLine("D-doCheckOnline; create $ntdstat");
                        $ntd = Ntd::createNTDurl($abusecontact_id,$record, $ntdstat);
                    } else {
                        ertLog::logLine("E-doCheckOnline; NO POLICE ABUSECONTACT SET");
                    }

                    if (ertICCAM2ERT::isActive() && $record->reference!='') {

                        // ICCAM set ERT_ICCAM_ACTION_LEA
                        ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                            'record_type' => class_basename($record),
                            'record_id' => $record->id,
                            'object_id' => $record->reference,
                            'action_id' => ERT_ICCAM_ACTION_LEA,
                            'country' => 'NL',
                            'reason' => 'ERT reported to LEA',
                        ]);

                    }

                } else {

                    $abusecontact_id =  $record->host_abusecontact_id;

                    $ntdstat = ERT_NTD_STATUS_QUEUE_DIRECTLY;
                    ertLog::logLine("D-doCheckOnline; create $ntdstat");
                    $ntd = Ntd::createNTDurl($abusecontact_id,$record, $ntdstat);

                    if ($ntd) {

                        // if NTD created, then valid

                        if (ertICCAM2ERT::isActive() && $record->reference!='') {

                            // ICCAM set ERT_ICCAM_ACTION_ISP
                            ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                'record_type' => class_basename($record),
                                'record_id' => $record->id,
                                'object_id' => $record->reference,
                                'action_id' => ERT_ICCAM_ACTION_ISP,
                                'country' => 'NL',
                                'reason' => 'ERT reported to ISP',
                            ]);

                        }

                        // check if site_owner rule -> send also NTD

                        if ($siteownerac_id = ertRules::checkSiteOwnerRule($record->url) ) {
                            $siteownerac = Abusecontact::find($siteownerac_id);
                            if ($siteownerac) {
                                ertLog::logLine("D-doCheckOnline; create hoster NTD (DIRECT + GROUPING) also for site_owner: $siteownerac->owner ");
                                $ntd = Ntd::createNTDurl($siteownerac_id, $record, ERT_NTD_STATUS_QUEUE_DIRECTLY);
                                if ($ntd) {
                                    // create one grouping -> sent after 24 hour when still online
                                    $ntd = Ntd::createNTDurl($siteownerac_id, $record);
                                }
                            } else {
                                ertLog::logLine("W-doCheckOnline; can not find siteownerac_id=$siteownerac_id ");
                            }
                        }

                        // if video stop

                        if ($record->url_type == ERT_URL_TYPE_VIDEOURL) {

                            $status = "Stop check online - VIDEO imageurl " ;
                            $job_records[] = [
                                'filenumber' => $record->filenumber,
                                'url' => $record->url,
                                'status' => $status,
                            ];

                            // close
                            $record->status_code = ERT_STATUS_CLOSE;
                            $record->logText($status);

                        } else {

                            // create one grouping -> sent when still online
                            $ntd = Ntd::createNTDurl($record->host_abusecontact_id,  $record);

                            // REGISTRAR -> create NTD each registar_interval

                            //ertLog::logLine("D-doCheckOnline; create NTD for registrar (registrar_interval=$registrar_interval)");
                            $ntd = Ntd::createNTDurl($record->registrar_abusecontact_id, $record, ERT_NTD_STATUS_GROUPING, $registrar_interval);

                        }

                    } else  {

                        // NO NTD created -> not NL and/or GDPR

                        $status = "Stop check online - wait for analist (CHANGED) - not in NL and/or GDPR approved; hoster is: $abusecontact->owner" ;
                        $job_records[] = [
                            'filenumber' => $record->filenumber,
                            'url' => $record->url,
                            'status' => $status,
                        ];

                        // close ->
                        $record->status_code = ERT_STATUS_ABUSECONTACT_CHANGED;

                        ertLog::logLine("W-$status");

                    }

                }

            } else {

                ertLog::logLine("W-doCheckOnline; no host abusecontact is set for input (id=$record->id)");

                $status = "Stop check online - wait for analist (CHANGED) - NO ABUSECONTACT SET " ;
                $job_records[] = [
                    'filenumber' => $record->filenumber,
                    'url' => $record->url,
                    'status' => $status,
                ];

                // close ->
                $record->status_code = ERT_STATUS_ABUSECONTACT_CHANGED;

            }

            $record->online_counter += 1;
            $record->lastseen_at = date('Y-m-d H:i:s');
            $record->save();

            $record->logText('Set status_code='.$record->status_code);

        } elseif ($record->status_code != ERT_STATUS_FIRST_POLICE)  {

            // last check longer then (check_online_every) ago

            ertLog::logLine("D-doCheckOnline; it's checkonline time (!) for content (id=$record->id); lastseen is more then $check_online_every minutes ago");

            // NO CACHE (!)
            ertBrowser::setCached(false);

            // get images or image
            $images = ertBrowser::getImages($record->url, $record->url_referer);
            $imgcnt = ($images) ? count($images) : 0;
            // do not log each time
            //$input->logText("Input (link) $input->url CHECKONLINE; found $imgcnt image" . (($imgcnt > 1) ? 's' : ''));

            if ($imgcnt > 0 && $record->url_type == ERT_URL_TYPE_IMAGEURL) {

                // if url_type=imageurl then check image -> first image = image
                reset($images);
                $image = current($images);
                $online = ($record->url_hash == $image['hash']);

            } elseif ($imgcnt > 0 && $record->url_type == ERT_URL_TYPE_MAINURL) {

                // if url_type=mainurl

                // ONLINE = if images found
                $online = true;

                // TO-DO; test hash on screenshot? -> what if datetime on webpage?

            } else {

                // no image(s) / not found (anymore)
                $online = false;
            }

            if ($online) {

                // (still) ONLINE
                ertLog::logLine("D-doCheckOnline; notification (id=$record->id) still online");

                // reset first if set (eg browser error or maintenance webpage )
                if ($record->browse_error_retry > 0) {
                    $record->browse_error_retry = 0;
                    // save below
                }

                // verify WhoIs
                $whois = ertWhois::verifyWhoIs($record);

                if ($whois['status_success']) {

                    // reset first if set (eg last time whois error )
                    if ($record->whois_error_retry > 0) {
                        $record->whois_error_retry = 0;
                    }

                    // CHECK IF CHANGED ABUSECONTACT

                    if ($whois[ERT_REGISTRAR.'_changed'] || $whois[ERT_HOSTER.'_changed']) {

                        /**
                         * stop futher processing
                         *
                         * Note:
                         * - if hoster changed then url is removed from old hoster NTD
                         * - if registrar changed then url is removed from old registrar NTD
                         *
                         *
                         */

                        $status = "Stop checkonline - wait for analist (CHANGED) - abusecontact changed (hoster and/or registrar)";
                        ertLog::logLine("D-$status");

                        $job_records[] = [
                            'filenumber' => $record->filenumber,
                            'url' => $record->url,
                            'status' => $status,
                        ];

                        // set waiting for analist
                        $record->status_code = ERT_STATUS_ABUSECONTACT_CHANGED;
                        $record->logText("Set status_code on: " . $record->status_code);

                    } else {

                        // HOSTER

                        $ntd = Ntd::createNTDurl($record->host_abusecontact_id, $record);
                        if ($ntd) {

                            // check if site_owner rule

                            if (($siteownerac_id = ertRules::checkSiteOwnerRule($record->url))) {
                                $siteownerac = Abusecontact::find($siteownerac_id);
                                if ($siteownerac) {
                                    $ntd = Ntd::createNTDurl($siteownerac_id, $record);
                                } else {
                                    ertLog::logLine("W-doCheckOnline; can not find siteownerac_id=$siteownerac_id ");
                                }
                            }

                            // REGISTRAR -> create NTD each registar_interval

                            //ertLog::logLine("D-doCheckOnline; create NTD for registrar (registrar_interval=$registrar_interval)");
                            $ntd = Ntd::createNTDurl($record->registrar_abusecontact_id, $record, ERT_NTD_STATUS_GROUPING, $registrar_interval);

                        } else {

                            // NO NTD created -> HOSTER not NL and/or GDPR

                            $owner = ($record->host_abusecontact_id != 0) ? Abusecontact::find($record->host_abusecontact_id)->owner : ERT_ABUSECONTACT_OWNER_EMPTY;
                            $status = "Stop checkonline - wait for analist (CHANGED) - Abusecontact not in NL and/or GDPR approved; hoster is: $owner";
                            ertLog::logLine("D-$status");

                            $job_records[] = [
                                'filenumber' => $record->filenumber,
                                'url' => $record->url,
                                'status' => $status,
                            ];

                            // set waiting for analist
                            $record->status_code = ERT_STATUS_ABUSECONTACT_CHANGED;
                            $record->logText("Set status_code on: " . $record->status_code);

                        }

                    }

                    $record->online_counter += 1;
                    $record->lastseen_at = date('Y-m-d H:i:s');

                } else {

                    // retry WhoIs

                    $record->whois_error_retry += 1;
                    ertLog::logLine("D-doCheckOnline; WHOIS lookup error of url '$record->url'; whois_error_retry=$record->whois_error_retry");

                    if ($record->whois_error_retry > ERT_WHOIS_ERROR_MAX) {

                        // we can not get WHoIs info!? -> stop (online) check
                        $status = "Stop checkonline - wait for analist (CHANGED) - cannot get WhoIS and/or hoster owner is empty";
                        ertLog::logLine("D-$status");

                        $job_records[] = [
                            'filenumber' => $record->filenumber,
                            'url' => $record->url,
                            'status' => $status,
                        ];

                        // hoster (if found)
                        $ntd = Ntd::removeUrl($record->host_abusecontact_id, $record->url);
                        if ($ntd) $ntd->logText("Content '$record->url' OFFLINE; removed from NTD $ntd->filenumber");

                        // registrar (if found)
                        $ntd = Ntd::removeUrl($record->registrar_abusecontact_id, $record->url);
                        if ($ntd) $ntd->logText("Content '$record->url' OFFLINE; removed from NTD $ntd->filenumber");

                        // set waiting for analist
                        $record->status_code = ERT_STATUS_ABUSECONTACT_CHANGED;
                        $record->logText("Set status_code on: " . $record->status_code);

                        // SEND ALERT WARNING
                        $params = [
                            'filenumber' => $record->filenumber,
                            'url' => $record->url,
                        ];
                        ertAlerts::insertAlert(ERT_ALERT_LEVEL_WARNING,'reportertool.eokm::mail.whois_notfound_abusecontact', $params);

                    }

                }

            } else {

                // check ERT_BROWSE_ERROR_MAX to be sure it's not a network problem

                $record->browse_error_retry += 1;
                ertLog::logLine("D-doCheckOnline; offline/browser error (content offline?); '$record->url' offline or HASH changed; browse_error_retry=$record->browse_error_retry");

                if ($record->browse_error_retry > ERT_BROWSE_ERROR_MAX) {

                    // OFFLINE -> mark input & notifications (images) offline

                    ertLog::logLine("D-doCheckOnline; offline retry max reached (=$record->browse_error_retry) ");
                    $record->logText("doCheckOnline; offline retry max reached");

                    // hoster (if found)
                    $ntd = Ntd::removeUrl($record->host_abusecontact_id, $record->url);
                    if ($ntd) $ntd->logText("Content '$record->url' OFFLINE or HASH changed; removed from NTD $ntd->filenumber");
                    // registrar (if found)
                    $ntd = Ntd::removeUrl($record->registrar_abusecontact_id, $record->url);
                    if ($ntd) $ntd->logText("Content '$record->url' OFFLINE or HASH changed; removed from NTD $ntd->filenumber");

                    $record->status_code = ERT_STATUS_CLOSE_OFFLINE;
                    $record->logText("Set status_code on: " . $record->status_code);

                    $status = "STOP checkonline - OFFLINE or HASH changed";
                    ertLog::logLine("D-$status");

                    $job_records[] = [
                        'filenumber' => $record->filenumber,
                        'url' => $record->url,
                        'status' => $status,
                    ];

                    if (ertICCAM2ERT::isActive() && $record->reference!='') {

                        // ICCAM content removed
                        ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                            'record_type' => class_basename($record),
                            'record_id' => $record->id,
                            'object_id' => $record->reference,
                            'action_id' => ERT_ICCAM_ACTION_CR,
                            'country' => '',
                            'reason' => 'ERT found content removed',
                        ]);

                    }

                }

            }

            // save changed browse_error_retry and/or status_code
            $record->save();

        }

        return $job_records;
    }

}
