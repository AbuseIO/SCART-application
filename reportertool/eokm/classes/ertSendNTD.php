<?php namespace reportertool\eokm\classes;

use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Ntd;
use ReporterTool\EOKM\Models\Ntd_template;
use ReporterTool\EOKM\Models\Ntd_url;
use ReporterTool\EOKM\Models\Input;

class ertSendNTD {

    /**
     * Send waiting NTD's
     *
     *
     * @return array
     */

    public static function waitingNTD($scheduler_process_count) {

        $ntd_nots = [];

        // get waiting NTD's to be handled

        $ntds = Ntd::whereIn('status_code', [ERT_NTD_STATUS_GROUPING,ERT_NTD_STATUS_QUEUE_DIRECTLY,ERT_NTD_STATUS_QUEUE_DIRECTLY_POLICE])
            ->take($scheduler_process_count)->get();

        if (count($ntds) > 0) {

            ertLog::logLine("D-schedulerSendNTD; found waiting NTD(s), count=".count($ntds) );

            /**
             * Check if blocked day
             *
             * Note:
             * - no holding of direct NTD's (DIRECTLY and DIRECTLY_POLICE)
             * - if on blocked-day url got offline, then NTD can also be gone
             * - after block-day, NTD will be triggerd for sending (after hours)
             *
             * Do check one time, use many
             *
             */
            $blocked = ertBlockeddays::triggerNTDblocked();

            foreach ($ntds AS $ntd) {

                // get abusecontact
                $abusecontact = Abusecontact::find($ntd->abusecontact_id);

                // determine trigger
                $trigger = ($ntd->status_code == ERT_NTD_STATUS_QUEUE_DIRECTLY || $ntd->status_code == ERT_NTD_STATUS_QUEUE_DIRECTLY_POLICE);
                if (!$trigger) {

                    // update hours since created -> number of hours rounded downwards
                    $ntd->groupby_hour_count = round((((time() - strtotime($ntd->groupby_start))/3600) - 0.5),0);
                    if ($ntd->groupby_hour_count <= 0) $ntd->groupby_hour_count  = 0;
                    $ntd->save();

                    if (!$blocked) {
                        $trigger = ($ntd->groupby_hour_count >= $ntd->groupby_hour_threshold);
                    } else {
                        //ertLog::logLine("D-blocked day");
                    }

                }

                if ($trigger) {

                    // triggered for queued NTD
                    $stattrigger = ($ntd->status_code == ERT_NTD_STATUS_QUEUE_DIRECTLY || $ntd->status_code == ERT_NTD_STATUS_QUEUE_DIRECTLY_POLICE) ? '(*)' : '';
                    $hourtrigger = ($ntd->groupby_hour_count >= $ntd->groupby_hour_threshold) ? '(*)' : '';
                    ertLog::logLine("D-Set NTD up for sending; status=$ntd->status_code $stattrigger, online hours=$ntd->groupby_hour_count, groupby_hours=$ntd->groupby_hour_threshold $hourtrigger");

                    // Check ALWAYS & EXTRA if abusecontact GDPR and NL

                    if (!$abusecontact->gdpr_approved) {

                        // no GDPR

                        ertLog::logLine("D-schedulerSendNTD; abusecontact $abusecontact->owner has no GDPR approved - do not sent NTD");

                        $ntd->status_code = ERT_NTD_STATUS_CLOSE;
                        $ntd->status_time = date('Y-m-d H:i:s');
                        $ntd->save();

                        $ntd_not = [
                            'filenumber' => $ntd->filenumber,
                            'status' => $ntd->status_code . " (sending to abusecontact is not GDPR approved)",
                            'abusecontact' => $abusecontact->owner,
                        ];
                        $ntd_nots[] = $ntd_not;

                    } else if (!ertGrade::isNL($abusecontact->abusecountry)) {

                        // not NL

                        ertLog::logLine("D-schedulerSendNTD; abusecontact $abusecontact->owner not NL - country=$abusecontact->abusecountry ");

                        $ntd->status_code = ERT_NTD_STATUS_CLOSE;
                        $ntd->status_time = date('Y-m-d H:i:s');
                        $ntd->save();

                        $ntd_not = [
                            'filenumber' => $ntd->filenumber,
                            'status' => $ntd->status_code . " (abusecontact not in NL)",
                            'abusecontact' => $abusecontact->owner,
                        ];
                        $ntd_nots[] = $ntd_not;

                    } else {

                        // init false
                        $ntdsendvalid = false;

                        // get abusecontact NTD message template

                        $abuseemail = $abusecontact->abusecustom;

                        $msg = Ntd_template::find($abusecontact->ntd_template_id);
                        if ($msg=='') {
                            // strange -> pick first
                            ertLog::logLine("W-schedulerSendNTD; for abusecontact (id=$abusecontact->id) NO NTD template is set!?");
                            $msg = Ntd_template::first();
                        }
                        if ($msg) {
                            if (trim($abusecontact->ntd_msg_subject)!='') {
                                $ntd->msg_subject = $abusecontact->ntd_msg_subject;
                            } else {
                                $ntd->msg_subject = $msg->subject;
                            }
                            if (trim($abusecontact->ntd_msg_body)!='') {
                                $ntd->msg_body = $abusecontact->ntd_msg_body;
                            } else {
                                $ntd->msg_body = $msg->body;
                            }
                        }

                        if ($msg) {

                            if (trim($abuseemail)!='') {

                                // nb: constrain that msg_abusecontact contains a valid email address
                                $ntd->msg_abusecontact = $abuseemail;

                                // add filenumber ref to subject
                                $ntd->msg_subject = "[$ntd->filenumber] " . $ntd->msg_subject;

                                // table with <url>, <online-count>, <first-seen>, <note>

                                $abuselinks = '<table class="urls" border="0" cellpadding="2" cellspacing="0">' . "\n";
                                $abuselinks .= '<tr><th align="left">url</th><th align="left">IP</th><th align="left">first seen</th><th align="left">last seen</th><th align="left">note</th></tr>'. " \n";
                                $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
                                foreach ($ntdurls AS $ntdurl) {
                                    // get record with more info
                                    $record = ($ntdurl->record_type==ERT_INPUT_TYPE) ? Input::find($ntdurl->record_id) : Notification::find($ntdurl->record_id);
                                    if ($record) {
                                        // add line to layout
                                        $abuselinks .= "<tr><td>$ntdurl->url</td><td>$record->url_ip</td><td>$ntdurl->firstseen_at</td><td>$record->lastseen_at</td><td>$ntdurl->note</td></tr>" . " \n";
                                    } else {
                                        ertLog::logLine("E-Cannot find record from NTD-url; ntdurl=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");
                                    }
                                }
                                $abuselinks .= "</table>\n";

                                // @TO-DO; split message on groupby_num_count !?

                                $body = str_replace('<p>{'.'{'.'abuselinks'.'}'.'}</p>', $abuselinks, $ntd->msg_body);
                                $body = "subject = '$ntd->msg_subject'\n==\n" . $body;
                                //trace_log($body);

                                $to = $ntd->msg_abusecontact;
                                ertLog::logLine("D-schedulerSendNTD; send NTD  to: $to ");

                                // TO-DO: more? optional fields for including in template

                                $fields = [
                                    'abuselinks' => '(already replaced?!)',
                                    'owner' => $abusecontact->owner,
                                    'online_since' => $ntd->groupby_start,
                                ];

                                $message = ertMail::sendNTD($to,$ntd->msg_subject,$body,$fields);
                                if ($message) {
                                    // save returned (final) values
                                    $ntd->msg_ident = $message['id'];
                                    $ntd->msg_body = $message['body'];
                                } else {
                                    $ntd->msg_ident = '';
                                    ertLog::logLine("W-schedulerSendNTD; NTD send but got NO message_id; cannot verify delivery!?");
                                }
                                $ntd->msg_queued = date('Y-m-d H:i:s');

                                $ntdsendvalid = true;

                                ertLog::logLine("D-schedulerSendNTD; NTD send (message_id=$ntd->msg_ident for abusecontact owner=$abusecontact->owner (id=$abusecontact->id) ");

                            } else {
                                ertLog::logLine("E-schedulerSendNTD; NO abuse email set!? - abusecontact owner=$abusecontact->owner, id=$abusecontact->id ");
                            }

                        } else {
                            ertLog::logLine("E-schedulerSendNTD; NO NTD template within system - cannot do NTD actions!");
                        }


                        if ($ntdsendvalid) {

                            // save fields when valid
                            $ntd->status_code = ERT_NTD_STATUS_QUEUED;
                            $ntd->status_time = date('Y-m-d H:i:s');
                            $ntd->save();
                            $ntd->logText("NTD messaged fields filled and queued at $ntd->msg_queued for $to");

                        } else {

                            $ntd->status_code = ERT_NTD_STATUS_SENT_FAILED;
                            $ntd->status_time = date('Y-m-d H:i:s');
                            $ntd->save();

                            $ntd_not = [
                                'filenumber' => $ntd->filenumber,
                                'status' => $ntd->status_code . " (no abusecontact email or template set!?)",
                                'abusecontact' => $abusecontact->owner,
                            ];
                            $ntd_nots[] = $ntd_not;

                        }

                    }

                }

            }

        }

        return $ntd_nots;
    }


    public static function checkEXIM() {

        $ntd_nots = [];

        // get all the NTDS set on queued and check EXIM status

        // give mail system time to process queued NTD (2 mins)
        $min2before = date('Y-m-d H:i:s',time() - (2 * 60) );
        //ertLog::logLine("D-schedulerSendNTD; check NTD messages queued before $min2before" );
        $ntds = Ntd::where('status_code',ERT_NTD_STATUS_QUEUED)->where('msg_queued','<=',$min2before)->get();
        $ntdscnt = count($ntds);

        if ($ntdscnt > 0) {
            ertLog::logLine("D-schedulerSendNTD; determine EXIM status; found $ntdscnt NTD messages (before $min2before) waiting for mailservice response " );
            foreach ($ntds AS $ntd) {
                // check status -> ertEXIM logt
                $status = ertEXIM::getMTAstatus($ntd->msg_ident);
                if ($status != ERT_NTD_STATUS_NOT_YET) {
                    $ntd->status_code = $status;
                    $ntd->status_time = date('Y-m-d H:i:s');
                    $ntd->save();
                    $ntd->logText("NTD status set on: $ntd->status_code");
                    if ($status == ERT_NTD_STATUS_SENT_FAILED) {
                        $abusecontact = Abusecontact::find($ntd->abusecontact_id);
                        // send message to operator
                        $ntd_not = [
                            'filenumber' => $ntd->filenumber,
                            'status' => $ntd->status_code,
                            'abusecontact' => $abusecontact->owner,
                        ];
                        $ntd_nots[] = $ntd_not;
                    } elseif ($status == ERT_NTD_STATUS_SENT_SUCCES) {

                        // update ntd_url records with firstntd_at if NOT set
                        $ntd_urls = Ntd_url::where('ntd_id',$ntd->id)->get();
                        foreach ($ntd_urls AS $ntd_url) {
                            $record = ($ntd_url->record_type == ERT_INPUT_TYPE) ? Input::find($ntd_url->record_id) : Notification::find($ntd_url->record_id);
                            if ($record) {
                                // set firstntd_at if not set ->
                                if ($record->firstntd_at==NULL) {
                                    $record->firstntd_at = date('Y-m-d H:i:s');
                                    $record->save();
                                }
                            } // if not the record deleted/gone
                        }

                    }
                } else {
                    // @TO-DO check if ntd->status_time is not to long ago... (days)
                }
            }
        }

        return $ntd_nots;
    }


}
