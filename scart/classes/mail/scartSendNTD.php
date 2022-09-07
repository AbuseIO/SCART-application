<?php namespace abuseio\scart\classes\mail;

use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\whois\scartUpdateWhois;
use abuseio\scart\models\Addon;
use Config;
use Lang;
use October\Rain\Parse\Bracket;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_template;
use abuseio\scart\models\Ntd_url;
use abuseio\scart\models\Input;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\Blockedday;
use abuseio\scart\classes\classify\scartGrade;

class scartSendNTD {

    /**
     * Send waiting NTD's
     *
     *
     * @return array
     */

    public static function waitingNTD() {

        $ntd_nots = [];

        // get waiting NTD's to be handled

        $ntds = Ntd::whereIn('status_code',
            [SCART_NTD_STATUS_GROUPING,SCART_NTD_STATUS_QUEUE_DIRECTLY,SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE])
            ->get();

        if (count($ntds) > 0) {

            scartLog::logLine("D-schedulerSendNTD; found waiting NTD(s), count=".count($ntds) );

            /**
             * Check if blocked day
             *
             * Note:
             * - no holding of direct NTD's (DIRECTLY and DIRECTLY_POLICE)
             * - if on blocked-day url got offline, then NTD can also be gone
             * - after block-day, NTD will be triggered for sending (after hours)
             *
             * Do check one time general
             *
             */
            $blocked = self::triggerNTDblocked();

            // 2020/6/24/Gs: UPDATE; direct check for blocked -> if set, hold EVERY ntd

            if (!$blocked) {

                $ntdmsgs = [];

                foreach ($ntds AS $ntd) {

                    /**
                     * Important: on the moment we actually send the NTD, we double check if each url within the NTD still is valid
                     *
                     * Within this loop we check/do for each active NTD:
                     *
                     * - trigger time?  (NTD threshold hour is reached)
                     * - check if still GDPR approved (can be changed in NTD active time)
                     * - check if abuse contact still in local hotline country  (can be changed in NTD active time)
                     * - verify if every url within NTD still same hoster and NTD abuseemail
                     * - send NTD by API or by EMAIL
                     * - if EMAIL then collect urls for grouping on each abuse email address
                     * - set status on QUEUED
                     *
                     * Note: after this step, the status of the queued NTD is checked -> see checkEXIM()
                     *
                     */

                    // get abusecontact
                    $abusecontact = Abusecontact::find($ntd->abusecontact_id);

                    // determine trigger

                    // update hours since created -> number of hours rounded downwards
                    $ntd->groupby_hour_count = round((((time() - strtotime($ntd->groupby_start))/3600) - 0.5),0);
                    if ($ntd->groupby_hour_count <= 0) $ntd->groupby_hour_count  = 0;
                    $ntd->save();

                    $trigger = ($ntd->groupby_hour_count >= $ntd->groupby_hour_threshold);

                    if ($trigger) {

                        // triggered for queued NTD
                        $stattrigger = ($ntd->status_code == SCART_NTD_STATUS_QUEUE_DIRECTLY || $ntd->status_code == SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE) ? '(*)' : '';
                        $hourtrigger = ($ntd->groupby_hour_count >= $ntd->groupby_hour_threshold) ? '(*)' : '';
                        scartLog::logLine("D-Set NTD up for sending; status=$ntd->status_code $stattrigger, online hours=$ntd->groupby_hour_count, groupby_hours=$ntd->groupby_hour_threshold $hourtrigger");

                        $status_timestamp = '['.date('Y-m-d H:i:s').'] ';

                        // Check ALWAYS & EXTRA if abusecontact GDPR and local

                        if (!$abusecontact->gdpr_approved) {

                            // no GDPR

                            scartLog::logLine("D-schedulerSendNTD; abusecontact $abusecontact->owner has no GDPR approved - do not sent NTD");

                            $ntd->status_code = SCART_NTD_STATUS_CLOSE;
                            $ntd->status_time = date('Y-m-d H:i:s');
                            $ntd->save();

                            $ntd_not = [
                                'filenumber' => $ntd->filenumber,
                                'status' => $status_timestamp . $ntd->status_code . " (sending to abusecontact is not GDPR approved)",
                                'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                            ];
                            $ntd_nots[] = $ntd_not;

                        } else if (!scartGrade::isLocal($abusecontact->abusecountry)) {

                            // not local

                            scartLog::logLine("D-schedulerSendNTD; abusecontact $abusecontact->owner not hotline country - country=$abusecontact->abusecountry ");

                            $ntd->status_code = SCART_NTD_STATUS_CLOSE;
                            $ntd->status_time = date('Y-m-d H:i:s');
                            $ntd->save();

                            $ntd_not = [
                                'filenumber' => $ntd->filenumber,
                                'status' => $status_timestamp . $ntd->status_code . " (abusecontact not in hotline country)",
                                'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                            ];
                            $ntd_nots[] = $ntd_not;

                        } else {

                            $abuseemail = trim($abusecontact->abusecustom);

                            // validate (last time) if not hoster/site owner is changed

                            if (scartSendNTD::validateWHois($ntd,$status_timestamp,$abuseemail) == 0) {

                                // all urls removed from ntd -> close NTD

                                scartLog::logLine("D-schedulerSendNTD; validateWhois removed all urls - close NTD");

                                $ntd->status_code = SCART_NTD_STATUS_CLOSE;
                                $ntd->status_time = date('Y-m-d H:i:s');
                                $ntd->save();

                                $ntd_not = [
                                    'filenumber' => $ntd->filenumber,
                                    'status' => $status_timestamp . $ntd->status_code . " (urls closed by Whois validation)",
                                    'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                                ];
                                $ntd_nots[] = $ntd_not;

                            } else {

                                // init false
                                $ntdsetvalid = false;

                                // get abusecontact NTD message template

                                if ($abusecontact->ntd_type == SCART_NTD_TYPE_API) {

                                    // SEND BY API
                                    $ntd->type = SCART_NTD_TYPE_API;

                                    $addon = Addon::find($abusecontact->ntd_api_addon_id);
                                    if ($addon) {

                                        // group by IP -> API interface is based in sending urls for each IP

                                        $cnt = 0;
                                        $total = Ntd_url::where('ntd_id',$ntd->id)->count();
                                        $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
                                        $ipgroup = [];
                                        foreach ($ntdurls AS $ntdurl) {
                                            $record = Input::find($ntdurl->record_id);
                                            if ($record) {
                                                if (!isset($ipgroup[$record->url_ip])) $ipgroup[$record->url_ip] = [];
                                                $ipgroup[$record->url_ip][$record->id] = $record->url;

                                                // save in ntd_url last actual record info
                                                $ntdurl->url = $record->url;
                                                $ntdurl->firstseen_at = $record->firstseen_at;
                                                $ntdurl->lastseen_at= $record->lastseen_at;
                                                $ntdurl->online_counter = $record->online_counter;
                                                $ntdurl->ip = $record->url_ip;
                                                $ntdurl->save();
                                            } else {
                                                scartLog::logLine("E-schedulerSendNTD; cannot find record from NTD-url; ntdurl=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");
                                            }
                                        }

                                        $ntdsetvalid = true;
                                        $record = new \stdClass();
                                        foreach ($ipgroup AS $ip => $urls) {
                                            $record->url = implode("\n",$urls);
                                            $record->url_ip = $ip;
                                            if (!Addon::run($addon,$record)) {

                                                $ntdsetvalid = false;

                                                // Handle NTD API (interface) error -> push to CHANGED for analist

                                                $lasterror = Addon::getLastError($addon);
                                                scartLog::logLine("W-schedulerSendNTD; error from Addon; $lasterror " );

                                                foreach ($urls AS $recordid => $url) {

                                                    $input = Input::find($recordid);
                                                    if ($input) {

                                                        // be sure this url is removed from NTD
                                                        Ntd::removeUrlgrouping($url);

                                                        // log old/new for history
                                                        $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,"Detected hoster change before sending NTD");

                                                        // set waiting for analist
                                                        $input->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;
                                                        $input->save();

                                                        $input->logText("Error from NTD API: $lasterror");
                                                        $input->logText("Set status_code on: " . $input->status_code);

                                                    }

                                                }

                                                $ntd_not = [
                                                    'filenumber' => $ntd->filenumber,
                                                    'status' => $status_timestamp . "Error from NTD API for (some) urls with ip=$ip: $lasterror",
                                                    'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                                                ];
                                                $ntd_nots[] = $ntd_not;

                                            } else {
                                                $cnt += 1;
                                            }
                                        }

                                    } else {
                                        scartLog::logLine("E-schedulerSendNTD; API ADDON (id=$abusecontact->ntd_api_addon_id) not found!?!");
                                    }

                                    if ($ntdsetvalid) {

                                        // save fields when valid
                                        $ntd->status_code = SCART_NTD_STATUS_SENT_API_SUCCES;
                                        $ntd->status_time = date('Y-m-d H:i:s');
                                        $ntd->save();

                                        $ntd_not = [
                                            'filenumber' => $ntd->filenumber,
                                            'status' => $status_timestamp . $ntd->status_code . " - $cnt from $total urls sent by NTD API",
                                            'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                                        ];
                                        $ntd_nots[] = $ntd_not;

                                    } else {

                                        $ntd->status_code = SCART_NTD_STATUS_SENT_API_FAILED;
                                        $ntd->status_time = date('Y-m-d H:i:s');
                                        $ntd->save();

                                        $ntd_not = [
                                            'filenumber' => $ntd->filenumber,
                                            'status' => $status_timestamp . $ntd->status_code . " (error sending to NTD API)",
                                            'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                                        ];
                                        $ntd_nots[] = $ntd_not;

                                    }

                                    $ntd->logText("NTD sent by API; status=".$ntd->status_code);

                                } else {

                                    // SEND BY EMAIL

                                    $ntd->type = SCART_NTD_TYPE_EMAIL;

                                    if ($abuseemail) {

                                        $msg = Ntd_template::find($abusecontact->ntd_template_id);
                                        if ($msg=='') {
                                            // strange -> pick first
                                            scartLog::logLine("W-schedulerSendNTD; for abusecontact (id=$abusecontact->id) NO NTD template is set!?");
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
                                                $to = $ntd->msg_abusecontact = $abuseemail;

                                                // add filenumber ref to subject
                                                $ntd->msg_subject = "[$ntd->filenumber] " . $ntd->msg_subject;

                                                // 2020/1/15/Gs: check option

                                                // 2020/3/10/Gs: remove NOTE; sometimes privileges info is included

                                                // 2020/5/15/Gs: special treatement of police
                                                $isPolice = ($abusecontact->police_contact);

                                                $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();

                                                // group by abuse email address -> sent in one message (NTD) all urls in this loop

                                                if (!isset($ntdmsgs[$abuseemail])) {
                                                    $lines = [];
                                                } else {
                                                    $lines = $ntdmsgs[$abuseemail]->lines;
                                                }

                                                foreach ($ntdurls AS $ntdurl) {
                                                    $record = Input::find($ntdurl->record_id);
                                                    if ($record) {
                                                        if ($isPolice) {
                                                            $reason = $record->police_reason;
                                                        } else {
                                                            // Note: also in $ntdurl->note
                                                            $reason = (!empty($record->ntd_note)) ? $record->ntd_note : '';
                                                        }
                                                        //scartLog::logLine("D-isPolice=$isPolice, reason=$reason");

                                                        // unique urls

                                                        if (isset($lines[$record->url])) {

                                                            scartLog::logLine("W-schedulerSendNTD; record url '$record->url' already included - merge");

                                                        } else {

                                                            // use actual data

                                                            $lines[$record->url] = [
                                                                'url' => $record->url,
                                                                'reason' => $reason,
                                                                'url_ip' => $record->url_ip,
                                                                'firstseen_at' => $record->firstseen_at,
                                                                'lastseen_at' => $record->lastseen_at,
                                                            ];

                                                            // save in ntd_url last actual record info
                                                            $ntdurl->url = $record->url;
                                                            $ntdurl->firstseen_at = $record->firstseen_at;
                                                            $ntdurl->lastseen_at= $record->lastseen_at;
                                                            $ntdurl->online_counter = $record->online_counter;
                                                            $ntdurl->ip = $record->url_ip;
                                                            $ntdurl->save();

                                                        }

                                                    } else {
                                                        scartLog::logLine("E-Cannot find record from NTD-url; ntdurl=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");
                                                    }
                                                }

                                                if (!isset($ntdmsgs[$abuseemail])) {
                                                    // init first one with main fields
                                                    $ntdmsg = new \stdClass();
                                                    $ntdmsg->csv_attachment = $msg->csv_attachment;
                                                    $ntdmsg->isPolice = $isPolice;
                                                    $ntdmsg->add_only_url = $msg->add_only_url;
                                                    $ntdmsg->filenumber = $ntd->filenumber;
                                                    $ntdmsg->msg_subject = $ntd->msg_subject;
                                                    $ntdmsg->msg_body = $ntd->msg_body;
                                                    $ntdmsg->ntd_ids = [$ntd->id];
                                                    $ntdmsg->lines = $lines;
                                                } else {
                                                    // attach other NTD (merge)
                                                    $ntdmsg = $ntdmsgs[$abuseemail];
                                                    $ntdmsg->ntd_ids[] = $ntd->id;
                                                    $ntdmsg->lines = $lines;
                                                }
                                                $ntdmsgs[$abuseemail] = $ntdmsg;

                                                $ntdsetvalid = true;

                                            } else {
                                                scartLog::logLine("E-schedulerSendNTD; NO abuse email set!? - abusecontact owner=$abusecontact->owner, id=$abusecontact->id ");
                                            }

                                        } else {
                                            scartLog::logLine("E-schedulerSendNTD; NO NTD template within system - cannot do NTD actions!");
                                        }

                                    } else {
                                        scartLog::logLine("E-schedulerSendNTD; NO abuse email set!? - abusecontact owner=$abusecontact->owner, id=$abusecontact->id ");
                                    }

                                    if ($ntdsetvalid) {

                                        // save fields when valid
                                        $ntd->status_code = SCART_NTD_STATUS_QUEUED;
                                        $ntd->status_time = date('Y-m-d H:i:s');

                                    } else {

                                        $ntd->status_code = SCART_NTD_STATUS_SENT_FAILED;
                                        $ntd->status_time = date('Y-m-d H:i:s');

                                        $ntd_not = [
                                            'filenumber' => $ntd->filenumber,
                                            'status' => $status_timestamp . $ntd->status_code . " (no abusecontact email or template set!?)",
                                            'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                                        ];
                                        $ntd_nots[] = $ntd_not;

                                    }

                                    $ntd->save();

                                }

                            }

                        }

                    } else {
                        scartLog::logLine("D-Not yet send for NTD; $ntd->status_code, online hours=$ntd->groupby_hour_count, groupby_hours=$ntd->groupby_hour_threshold");
                    }

                }

                if (count($ntdmsgs) > 0) {

                    $lang = Lang::getLocale();

                    //

                    foreach ($ntdmsgs AS $abuseemail => $ntdmsg) {

                        if ($ntdmsg->csv_attachment) {

                            $csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
                            $csvtemp .= ($isPolice) ? 'ntdcsvfile-police.tpl' : (($ntdmsg->add_only_url) ? 'ntdcsvfile-onlyurl.tpl' : 'ntdcsvfile.tpl' );

                            $tmpdata = Bracket::parse(file_get_contents($csvtemp),['lines' => $ntdmsg->lines]);
                            $tmpfile = temp_path() . '/urls-'.$ntdmsg->filenumber.'.csv';
                            file_put_contents($tmpfile, $tmpdata);

                            $abuselinks = '(abuse urls in CSV attachment)';
                            scartLog::logLine("D-schedulerSendNTD; send NTD $ntdmsg->filenumber to '$abuseemail' with attachment '$tmpfile' ");

                        } else {

                            $csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
                            $csvtemp .= ($isPolice) ? 'ntdbody-police.tpl' : (($ntdmsg->add_only_url) ? 'ntdbody-onlyurl.tpl' : 'ntdbody.tpl' );

                            $abuselinks = Bracket::parse(file_get_contents($csvtemp),['lines' => $ntdmsg->lines]);
                            $tmpfile = '';
                            scartLog::logLine("D-schedulerSendNTD; send NTD $ntdmsg->filenumber to '$abuseemail' with urls included in body ");

                        }

                        /**
                         * @TO-DO
                         *
                         * When NOT <p>{{abuselinks}}</p> then within SendNTD the TABLE is not include in the BODY
                         * Very strange; investigate of mail function and html tags
                         *
                         */

                        //$ntd->msg_body = str_replace('{'.'{'.'abuselinks'.'}'.'}', $abuselinks, $ntd->msg_body);
                        $msg_body = str_replace('<p>{{'.'abuselinks'.'}}</p>', $abuselinks, $ntdmsg->msg_body);

                        $bcc_email = Systemconfig::get('abuseio.scart::scheduler.sendntd.bcc_email','');
                        if ($bcc_email) scartLog::logLine("D-schedulerSendNTD; send BCC to '$bcc_email'");

                        $message = scartMail::sendNTD($abuseemail,$ntdmsg->msg_subject,$msg_body,$bcc_email,$tmpfile);
                        if (!$message) {
                            scartLog::logLine("W-schedulerSendNTD; cannot deliver to queue; got NO message_id; cannot verify delivery!?");
                        }

                        foreach ($ntdmsg->ntd_ids AS $ind => $ntd_id) {

                            $ntd = Ntd::find($ntd_id);
                            $ntd->msg_subject = $ntdmsg->msg_subject;
                            $ntd->msg_body = $msg_body;
                            $ntd->msg_ident = ($message) ? $message['id'] : '?';
                            $ntd->msg_queued = date('Y-m-d H:i:s');

                            if (!$message) {

                                $ntd->status_code = SCART_NTD_STATUS_SENT_FAILED;
                                $ntd->status_time = date('Y-m-d H:i:s');

                                $ntd_not = [
                                    'filenumber' => $ntd->filenumber,
                                    'status' => $status_timestamp . $ntd->status_code . " cannot deliver to (local) message queue (no message id)",
                                    'abusecontact' => "email address: $abuseemail",
                                ];
                                $ntd_nots[] = $ntd_not;

                            }

                            $ntd->save();

                            if ($ind > 0) {
                                $ntd->logText("NTD merged with $ntdmsg->filenumber and msg-id '$ntd->msg_ident' at $ntd->msg_queued for $abuseemail");
                            } else {
                                $ntd->logText("NTD filled and queued with msg-id '$ntd->msg_ident' at $ntd->msg_queued for $abuseemail");
                            }

                        }

                        if ($tmpfile) {
                            @unlink($tmpfile);
                        }

                    }

                }

            } else {

                scartLog::logLine("D-blocked day");

            }

        }

        return $ntd_nots;
    }

    private static function validateWhois($ntd,$status_timestamp,$abuseemail) {

        scartLog::logLine("D-schedulerSendNTD; validateWhois for ntd_id=$ntd->id, abuseemail=$abuseemail ");

        // collect domains
        $proxy_update_domains = [];

        $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
        foreach ($ntdurls AS $ntdurl) {
            $record = Input::find($ntdurl->record_id);
            if ($record) {

                // set work var
                $domain = $record->url_host;

                /**
                 * FIRST check if domain of record has PROXY API; if yes then update
                 *
                 * Update is done within the proxy rule
                 *
                 */

                if (!isset($proxy_update_domains[$domain])) {

                    // check proxy service
                    scartUpdateWhois::checkUpdateProxyAPIrealIP($domain);

                    // check each domain only once
                    $proxy_update_domains[$domain] = $domain;
                }

                // Note: if proxy real IP rule is updated (above) then the verifyWhoIs detects this change and will take action if needed

                // verify WhoIs -> force no database cache
                $whois = scartWhois::verifyWhoIs($record,true,false);

                if ($whois['status_success']) {

                    // CHECK IF CHANGED HOSTER

                    if ($whois[SCART_HOSTER . '_changed']) {

                        scartLog::logLine("D-schedulerSendNTD; WhoIs changed for ntdurl_id=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");

                        // log change of hoster
                        $record->logText($whois[SCART_HOSTER . '_changed_logtext']);

                        $status = (($whois[SCART_HOSTER . '_changed_logtext']!='') ? $whois[SCART_HOSTER . '_changed_logtext'] : '');
                        $status = "Stop sending NTD - check analist (CHANGED) - $status";
                        scartLog::logLine("D-$status");

                        // be sure this url is removed from (any) NTD
                        Ntd::removeUrlgrouping($record->url);

                        // log old/new for history
                        $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,"Detected hoster change for url within NTD");

                        // set waiting for analist
                        $record->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;

                        $record->logText($status);
                        $record->logText("Set status_code on: " . $record->status_code);

                    }

                    // always save because of possible changes in scartWhois::verifyWhoIs
                    $record->save();

                }  // ignore

            } else {
                scartLog::logLine("E-schedulerSendNTD; cannot find record from NTD-url; ntdurl=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");
            }
        }

        // return number of urls in NTD -> if 0 then no more
        return Ntd_url::where('ntd_id',$ntd->id)->count();
    }

    public static function checkEXIM() {

        $ntd_nots = [];

        // get all the NTDS set on queued and check EXIM status

        // give mail system time to process queued NTD (2 mins)
        $min2before = date('Y-m-d H:i:s',time() - (2 * 60) );
        //scartLog::logLine("D-schedulerSendNTD; check NTD messages queued before $min2before" );
        $ntds = Ntd::where('status_code',SCART_NTD_STATUS_QUEUED)->where('msg_queued','<=',$min2before)->get();
        $ntdscnt = count($ntds);

        if ($ntdscnt > 0) {
            scartLog::logLine("D-schedulerSendNTD; determine EXIM status; found $ntdscnt NTD messages (before $min2before) waiting for mailservice response " );
            foreach ($ntds AS $ntd) {
                // check status -> scartEXIM logt
                $status_timestamp = '['.date('Y-m-d H:i:s').'] ';
                $status = scartEXIM::getMTAstatus($ntd->msg_ident);
                if ($status != SCART_NTD_STATUS_NOT_YET) {
                    $ntd->status_code = $status;
                    $ntd->status_time = date('Y-m-d H:i:s');
                    $ntd->save();
                    $ntd->logText("NTD status set on: $ntd->status_code");
                    if ($status == SCART_NTD_STATUS_SENT_FAILED) {

                        $abusecontact = Abusecontact::find($ntd->abusecontact_id);
                        // send message to operator
                        $ntd_not = [
                            'filenumber' => $ntd->filenumber,
                            'status' => $status_timestamp . $ntd->status_code,
                            'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                        ];
                        $ntd_nots[] = $ntd_not;
                    } elseif ($status == SCART_NTD_STATUS_SENT_SUCCES) {

                        $abusecontact = Abusecontact::find($ntd->abusecontact_id);

                        // update ntd_url records with firstntd_at if NOT set
                        $ntd_urls = Ntd_url::where('ntd_id',$ntd->id)->get();
                        foreach ($ntd_urls AS $ntd_url) {
                            $record = Input::where('id',$ntd_url->record_id)->whereNull('firstntd_at')->first();
                            if ($record) {
                                $record->firstntd_at = date('Y-m-d H:i:s');
                                $record->save();
                            }
                        }

                        $counturls = Ntd_url::where('ntd_id',$ntd->id)->count();
                        $ntd_not = [
                            'filenumber' => $ntd->filenumber,
                            'status' => $status_timestamp . 'NTD sent success; number of urls in attachment: ' . $counturls,
                            'abusecontact' => $abusecontact->owner . " ($abusecontact->filenumber)",
                        ];
                        $ntd_nots[] = $ntd_not;

                    }
                } else {
                    // @TO-DO check if ntd->status_time is not to long ago... (days)
                }
            }
        }

        return $ntd_nots;
    }

    /**
     * Check if blocked-day or weekend
     *
     * @return bool
     */

    public static function triggerNTDblocked() {

        $active = Systemconfig::get('abuseio.scart::ntd.use_blockeddays', true);
        if ($active) {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $tomorrow = date('Y-m-d', strtotime('+1 day'));

            $hourmin = date('H:i');
            $dayofweek = date('N');

            $blocktext = '';
            if ($dayofweek == 6 || $dayofweek == 7) {
                $blocked = true;
                $blocktext = 'weekend';
            } elseif (Blockedday::where('day',$today)->count() > 0) {
                $blocked = true;
                $blocktext = 'blocked day';
            } elseif ((Blockedday::where('day',$yesterday)->count() > 0) || ($dayofweek == 1)) {
                // YESTERDAY was blocked day OR monday
                // day after block or weekend -> check if before 12:00 (default) -> then also blocked
                $after_blockedday_hours = Systemconfig::get('abuseio.scart::ntd.after_blockedday_hours', "12:00");
                $blocked = ($hourmin < $after_blockedday_hours);
                if ($blocked) $blocktext = "before $after_blockedday_hours and yesterday was blocked or weekend day";
            } elseif ((Blockedday::where('day',$tomorrow)->count() > 0) || ($dayofweek == 5)) {
                // TOMORROW is blocked day OR friday
                // day before block or weekend -> check if after 16:30 (default) -> then also blocked
                $before_blockedday_hours = Systemconfig::get('abuseio.scart::ntd.before_blockedday_hours', "16:30");
                $blocked = ($hourmin > $before_blockedday_hours);
                if ($blocked) $blocktext = "after $before_blockedday_hours and tomorrow is blocked or weekend day";
            } else {
                $blocked = false;
            }
            // if not blocked check on every working day if after hours
            if (!$blocked && ($dayofweek >=1 || $dayofweek <= 5)) {
                // normal day -> do not start sending NTD before "after_hours"
                $after_hours = Systemconfig::get('abuseio.scart::ntd.after_hours', "11:00");
                $blocked = ($hourmin < $after_hours);
                if ($blocked) $blocktext = "on working day and before $after_hours";
            }
            if ($blocked) {
                scartLog::logLine("D-ertBlockeddays; BLOCKED; dayofweek=$dayofweek, today=$today, yesterday=$yesterday, tomorrow=$tomorrow, hourmin=$hourmin; blocked day reason: $blocktext");
            } else {
                scartLog::logLine("D-ertBlockeddays; NOT BLOCKED; dayofweek=$dayofweek, today=$today, yesterday=$yesterday, tomorrow=$tomorrow, hourmin=$hourmin");
            }
        } else {
            $blocked = false;
        }
        return $blocked;
    }

}
