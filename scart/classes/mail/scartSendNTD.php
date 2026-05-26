<?php namespace abuseio\scart\classes\mail;

use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\whois\scartUpdateWhois;
use abuseio\scart\models\Addon;
use abuseio\scart\Models\ImportWebform;
use abuseio\scart\models\Scrape_cache;
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
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Winter\Storm\Parse\Twig;

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

            if (!self::triggerNTDblocked()) {

                $ntdmsgs = [];

                foreach ($ntds AS $ntd) {

                    /**
                     * Important: on this moment we actually send the NTD, we double check if each url within the NTD still is valid
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

                            if (scartSendNTD::validateWHois($ntd,$abuseemail) == 0) {

                                // here all the urls are removed from ntd, so close NTD

                                scartLog::logLine("D-schedulerSendNTD; no urls (anymore) atatched to NTD - skip sending and close NTD");

                                $ntd->status_code = SCART_NTD_STATUS_CLOSE;
                                $ntd->status_time = date('Y-m-d H:i:s');
                                $ntd->save();

                                $ntd_not = [
                                    'filenumber' => $ntd->filenumber,
                                    'status' => $status_timestamp . $ntd->status_code . " (no urls (anymore) attached to NTD)",
                                    'abusecontact' => $abusecontact->owner. " ($abusecontact->filenumber)",
                                ];
                                $ntd_nots[] = $ntd_not;

                            } else {

                                if ($abusecontact->ntd_type == SCART_NTD_TYPE_API) {
                                    $ntd_nots = array_merge($ntd_nots,self::sendByApi($ntd,$abusecontact,$status_timestamp));
                                } else {
                                    $ntd_nots = array_merge($ntd_nots,self::sendByEmail($ntd,$abusecontact,$status_timestamp,$ntdmsgs));
                                }

                            }

                        }

                    } else {
                        scartLog::logLine("D-Not yet send for NTD; $ntd->status_code, online hours=$ntd->groupby_hour_count, groupby_hours=$ntd->groupby_hour_threshold");
                    }

                }

                if (count($ntdmsgs) > 0) {
                    // send grouped NTD (urls) in one NTD message
                    $ntd_nots = array_merge($ntd_nots,self::sendGroupedMessages($ntdmsgs,$status_timestamp));
                }


            } else {

                scartLog::logLine("D-blocked day");

            }

        }

        return $ntd_nots;
    }

    public static function sendDirectNtd($ntd) {

        $result = '';

        $abusecontact = Abusecontact::find($ntd->abusecontact_id);
        $status_timestamp = '['.date('Y-m-d H:i:s').'] ';
        $ntdmsgs = [];

        // Check ALWAYS & EXTRA if abusecontact GDPR and local

        if (!$abusecontact->gdpr_approved) {

            // no GDPR
            $result = "Abusecontact $abusecontact->owner has no GDPR approved - not sent NTD";

        } else if (!scartGrade::isLocal($abusecontact->abusecountry)) {

            // not local
            $result =  "Abusecontact $abusecontact->owner not local hotline country - country=$abusecontact->abusecountry ";

        } else {

            $abuseemail = trim($abusecontact->abusecustom);

            if (scartSendNTD::validateWHois($ntd,$abuseemail) != 0) {

                if ($abusecontact->ntd_type == SCART_NTD_TYPE_API) {
                    // push to API
                    self::sendByApi($ntd,$abusecontact,$status_timestamp);
                } else {
                    // push into $ntdmsgs array for grouping together
                    self::sendByEmail($ntd,$abusecontact,$status_timestamp,$ntdmsgs);
                }

                if (in_array($ntd->status_code,[SCART_NTD_STATUS_SENT_API_SUCCES,SCART_NTD_STATUS_QUEUED])) {

                    if (count($ntdmsgs) > 0) {

                        //scartLog::logDump("D-sendDirectNtd; ntdmsgs=",$ntdmsgs);

                        // send grouped NTD (urls) in one NTD message (email)
                        if (empty(self::sendGroupedMessages($ntdmsgs,$status_timestamp))) {

                            $ntd->status_code = SCART_NTD_STATUS_SENT_SUCCES;
                            $ntd->save();

                            $ntd->logText('NTD send again - set on: '.$ntd->status_code);
                            $result = "NTD send success";

                        } else {
                            $result = "NTD send failed";
                        }

                    } else {
                        $result = "NTD send by API success";
                    }

                } else {
                    $result = "NTD send failed";
                }

            } else {
                $result = "No URLs are attached (anymore) to this NTD - skip sending";
            }

        }

        return $result;
    }

    private static function sendByApi($ntd,$abusecontact,$status_timestamp) {

        // SEND BY API
        $ntd->type = SCART_NTD_TYPE_API;
        $ntd_nots = [];

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
            $ntdsetvalid = false;
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

            $ntd->logText("NTD sent by API; status=".$ntd->status_code);

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

            $ntd->logText("NTD sent by API failed; status=".$ntd->status_code);
        }

        return $ntd_nots;
    }

    /**
     * Collect all to-send-messages with one or more ntds. Return array with (error) notices
     *
     * @param $ntd
     * @param $abusecontact
     * @param $status_timestamp
     * @param $ntdmsgs
     * @return array
     *
     */

    private static function sendByEmail($ntd,$abusecontact,$status_timestamp,&$ntdmsgs) {

        $ntd_nots = $linesheader = [];

        $ntd->type = SCART_NTD_TYPE_EMAIL;

        $ntdsetvalid = false;

        $abuseemail = trim($abusecontact->abusecustom);

        if ($abuseemail) {

            // Group NTD's together based on the abuse receiving email address into $ntdmsgs

            if ($template = self::getTemplate($abusecontact,$ntd)) {

                // fill message receiving fields
                $ntd->msg_abusecontact = $abuseemail;  // constrain that it is a valid email address
                // add filenumber ref to subject
                $ntd->msg_subject = "[$ntd->filenumber] " . $ntd->msg_subject;

                // file lines with the info for each include url; also get unique (lines)header
                $lines = self::getUrlLines($ntd,$abusecontact,$linesheader);

                // fill message foreach abuseemail with one or more ntds
                $ntdmsg = (isset($ntdmsgs[$abuseemail]) ? $ntdmsgs[$abuseemail] : false);
                $ntdmsgs[$abuseemail] = self::getNtdmsg($ntdmsg,$abusecontact,$template,$ntd,$lines,$linesheader);

                $ntdsetvalid = true;
            } else {
                scartLog::logLine("E-schedulerSendNTD; NO NTD template within system - cannot do NTD actions!?");
            }

        } else {
            scartLog::logLine("E-schedulerSendNTD; NO abuse email set!? - abusecontact owner={$abusecontact->owner}, id={$abusecontact->id} ");
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

            $ntd->logText("NTD set on send by email failed; status=".$ntd->status_code);
        }

        // don't forget to save the updates
        $ntd->save();

        return $ntd_nots;
    }

    private static function getTemplate($abusecontact,$ntd) {

        $template = Ntd_template::find($abusecontact->ntd_template_id);
        if ($template=='') {
            // strange -> pick first
            scartLog::logLine("W-schedulerSendNTD; for abusecontact (id=$abusecontact->id) NO NTD template is set!?");
            $template = Ntd_template::first();
        }
        if ($template) {
            if (trim($abusecontact->ntd_msg_subject)!='') {
                $ntd->msg_subject = $abusecontact->ntd_msg_subject;
            } else {
                $ntd->msg_subject = $template->subject;
            }
            if (trim($abusecontact->ntd_msg_body)!='') {
                $ntd->msg_body = $abusecontact->ntd_msg_body;
            } else {
                $ntd->msg_body = $template->body;
            }
        }

        return $template;
    }

    private static function getNtdmsg($ntdmsg,$abusecontact,$template,$ntd,$lines,$linesheader) {

        if (!$ntdmsg) {
            // init first one with main fields
            $ntdmsg = new \stdClass();
            $ntdmsg->csv_attachment = $template->csv_attachment;
            $ntdmsg->isPolice = $abusecontact->police_contact;
            $ntdmsg->isLea = (Systemconfig::get('abuseio.scart::options.lea_function',false) && $abusecontact->lea_contact);
            $ntdmsg->add_only_url = $template->add_only_url;
            $ntdmsg->filenumber = $ntd->filenumber;
            $ntdmsg->msg_subject = $ntd->msg_subject;
            $ntdmsg->msg_body = $ntd->msg_body;
            $ntdmsg->ntd_ids = $ntdmsg->lines = $ntdmsg->webformheaders = [];
        }
        // merge
        $ntdmsg->ntd_ids[] = $ntd->id;
        $ntdmsg->lines = array_merge($ntdmsg->lines,$lines);
        $ntdmsg->webformheaders = array_merge($ntdmsg->webformheaders,$linesheader);
        return $ntdmsg;
    }

    private static function getUrlLines($ntd,$abusecontact,&$linesheader)
    {
        if (Systemconfig::get('abuseio.scart::options.lea_function', false) && $abusecontact->lea_contact) {
            $lines = self::getUrlLinesLEA($ntd, $abusecontact, $linesheader);
        } else {
            $lines = self::getUrlLinesStandard($ntd, $abusecontact, $linesheader);
        }
        return $lines;
    }

    private static function getUrlLinesLEA($ntd,$abusecontact,&$linesheader)
    {
        /**
         * LEA NTD can be:
         *
         * 1. Webform: MAINURL with content and headers and optional image items as attachment
         * 2. Other: urls with standard header fields
         *
         */

        // check if EXTRAFIELD_WEBFORM SUBJECT exists -> if yes, then LEA Webform

        // @todo; check on MAINURL parent...!

        $is_webform = Ntd_url::where('ntd_id', $ntd->id)
            ->join(SCART_INPUT_TABLE, SCART_INPUT_TABLE . '.id', '=', SCART_NTD_URL_TABLE . '.record_id')
            ->where(SCART_INPUT_TABLE . '.url_type', SCART_URL_TYPE_MAINURL)
            ->join(SCART_INPUT_EXTRA_TABLE, SCART_INPUT_EXTRA_TABLE . '.input_id', '=', SCART_NTD_URL_TABLE . '.record_id')
            ->where(SCART_INPUT_EXTRA_TABLE . '.type', SCART_INPUT_EXTRAFIELD_WEBFORM)
            ->where(SCART_INPUT_EXTRA_TABLE . '.label', SCART_INPUT_EXTRAFIELD_WEBFORM_SUBJECT)
            ->select(SCART_INPUT_TABLE . '.id')
            ->exists();
        if ($is_webform) {
            scartLog::logLine("D-schedulerSendNTD.getUrlLinesLEA; is WEBFORM");
            $lines = self::getUrlLinesLEAwebform($ntd, $abusecontact, $linesheader);
        } else {
            scartLog::logLine("D-schedulerSendNTD.getUrlLinesLEA; is normal record");
            $lines = self::getUrlLinesLEAstandard($ntd, $abusecontact, $linesheader);
        }
        return $lines;
    }

    private static function getUrlLinesLEAwebform($ntd,$abusecontact,&$linesheader) {

        $lines = $parents = [];

        $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
        foreach ($ntdurls AS $ntdurl) {
            $record = Input::find($ntdurl->record_id);
            if ($record) {

                // unique urls - note: when duplicates ntdurls->url, then the last one overwrites

                if (($webformsubject = $record->getExtrafieldValue(SCART_INPUT_EXTRAFIELD_WEBFORM,SCART_INPUT_EXTRAFIELD_WEBFORM_SUBJECT)) &&
                    ($webform = ImportWebform::where('subject',$webformsubject)->first()))
                {
                    scartLog::logLine("D-schedulerSendNTD.getUrlLinesLEAwebform; [$record->filenumber] add importWebform fields for webform subject '$webformsubject'");

                    if (!$webform->ntd_use_standard_fields) {

                        // Use WEBFORM fields in NTD body

                        foreach ($webform->fields as $field) {
                            //scartLog::logDump("D-schedulerSendNTD; webform field: ",$field->toArray());
                            if ($field->import_fieldname == SCART_IMPORT_WEBFORM_ATTRIBUTE) {
                                if (!$field->not_lea) {
                                    $webformfields[$field->name] = $record->getExtrafieldValue(SCART_INPUT_EXTRAFIELD_WEBFORM,$field->name);
                                    $linesheader[$field->name] = $field->name;
                                }
                            } elseif ($field->import_fieldname == SCART_IMPORT_WEBFORM_REFERER) {
                                $webformfields[SCART_IMPORT_WEBFORM_REFERER] = $record->url_referer;
                                $linesheader[SCART_IMPORT_WEBFORM_REFERER] = SCART_IMPORT_WEBFORM_REFERER;
                            } elseif ($field->import_fieldname == SCART_IMPORT_WEBFORM_NOTE) {
                                $webformfields[SCART_IMPORT_WEBFORM_NOTE] = $record->note;
                                $linesheader[SCART_IMPORT_WEBFORM_NOTE] = SCART_IMPORT_WEBFORM_NOTE;
                            } elseif ($field->import_fieldname == SCART_IMPORT_WEBFORM_NTD_NOTE) {
                                $webformfields[SCART_IMPORT_WEBFORM_NTD_NOTE] = $record->ntd_note;
                                $linesheader[SCART_IMPORT_WEBFORM_NTD_NOTE] = SCART_IMPORT_WEBFORM_NTD_NOTE;
                            } elseif ($field->import_fieldname == SCART_IMPORT_WEBFORM_URL) {
                                $webformfields[SCART_IMPORT_WEBFORM_URL] = $record->url;
                                $linesheader[SCART_IMPORT_WEBFORM_URL] = SCART_IMPORT_WEBFORM_URL;
                            } // ignore SCART_IMPORT_WEBFORM_IMAGE
                        }

                        // add always ntd_note (LEA note popup field)
                        $webformfields[SCART_IMPORT_WEBFORM_NTD_NOTE] = $record->ntd_note;
                        $linesheader[SCART_IMPORT_WEBFORM_NTD_NOTE] = SCART_IMPORT_WEBFORM_NTD_NOTE;

                    } else {

                        // Use standard fields in NTD body

                        $webformfields = [
                            'filenumber' => $record->filenumber,
                            'url' => $record->url,
                            'hosting country' => (($record->abusecontact) ? $record->abusecontact->abusecountry: ''),
                            'IP' => $record->url_ip,
                            'hoster' => (($record->abusecontact) ? $record->abusecontact->owner : ''),
                            'referrer' => $record->url_referer,
                            'memo' => $record->note,
                            'NTD note' => $record->ntd_note,
                        ];

                        $linesheader = [
                            'filenumber' => 'filenumber',
                            'url' => 'url',
                            'hosting country' => 'hosting country',
                            'IP' => 'IP',
                            'hoster' => 'hoster',
                            'referer' => 'referer',
                            'memo' => 'memo',
                            'NTD note' => 'NTD note',
                        ];

                    }

                    $lines[$record->url] = $webformfields;

                    scartLog::logDump("D-schedulerSendNTD.getUrlLinesLEAwebform; [$record->filenumber], subject=$webformsubject, linesheader: ",$linesheader);

                } else {

                    // special path for NOT mainurl with webform extra fields -> add HASH so image can be added

                    // Note: hash is most important for adding image to NTD

                    if (empty($linesheader)) {
                        $linesheader = [
                            'filenumber' => 'filenumber',
                            'url' => 'url',
                            'hosting country' => 'hosting country',
                            'IP' => 'IP',
                            'hoster' => 'hoster',
                            'referer' => 'referer',
                            'memo' => 'memo',
                            'NTD note' => 'NTD note',
                            'hash' => 'hash',               // hash for adding images -> MARKER!
                        ];
                    }
                    $lines[$record->url] = [
                        'filenumber' => $record->filenumber,
                        'url' => $record->url,
                        'hosting country' => (($record->abusecontact) ? $record->abusecontact->abusecountry: ''),
                        'IP' => $record->url_ip,
                        'hoster' => (($record->abusecontact) ? $record->abusecontact->owner : ''),
                        'referrer' => $record->url_referer,
                        'memo' => $record->note,
                        'NTD note' => $record->ntd_note,
                        'hash' => $record->url_hash,
                    ];
                }

                // save in ntd_url last actual record info
                $ntdurl->firstseen_at = $record->firstseen_at;
                $ntdurl->lastseen_at= $record->lastseen_at;
                $ntdurl->online_counter = $record->online_counter;
                $ntdurl->ip = $record->url_ip;
                $ntdurl->save();

            } else {
                scartLog::logLine("E-schedulerSendNTD.getUrlLinesLEAwebform; cannot find record from NTD-url; ntdurl=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");
                // skip this one
            }
        }

        return $lines;

    }

    private static function getUrlLinesLEAstandard($ntd,$abusecontact,&$linesheader) {

        $lines = [];

        $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
        foreach ($ntdurls AS $ntdurl) {
            $record = Input::find($ntdurl->record_id);
            if ($record) {

                scartLog::logLine("D-schedulerSendNTD.getUrlLinesLEAstandard; [$record->filenumber] add standard NTD fields");

                // use actual data
                $lines[$record->url] = [
                    'filenumber' => $record->filenumber,
                    'url' => $record->url,
                    'hosting country' => (($record->abusecontact) ? $record->abusecontact->abusecountry: ''),
                    'IP' => $record->url_ip,
                    'hoster' => (($record->abusecontact) ? $record->abusecontact->owner : ''),
                    'referrer' => $record->url_referer,
                    'memo' => $record->note,
                    'NTD note' => $record->ntd_note,

//                    'url' => $record->url,
//                    'host' => $record->hoster->owner,
//                    'host country' => $record->hoster->abusecountry,
//                    'referer' => $record->url_referer,
//                    'note' => $record->note,
//                    'ntd_note' => $record->ntd_note,
                ];
                if (empty($linesheader)) {
                    $linesheader = [
                        'filenumber' => 'filenumber',
                        'url' => 'url',
                        'hosting country' => 'hosting country',
                        'IP' => 'IP',
                        'hoster' => 'hoster',
                        'referer' => 'referer',
                        'memo' => 'memo',
                        'NTD note' => 'NTD note',
                    ];
                }

                // save in ntd_url last actual record info
                $ntdurl->firstseen_at = $record->firstseen_at;
                $ntdurl->lastseen_at= $record->lastseen_at;
                $ntdurl->online_counter = $record->online_counter;
                $ntdurl->ip = $record->url_ip;
                $ntdurl->save();

            } else {
                scartLog::logLine("E-schedulerSendNTD.getUrlLinesLEAstandard; cannot find record from NTD-url; ntdurl=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");
                // skip this one
            }
        }

        return $lines;
    }

    private static function getUrlLinesStandard($ntd,$abusecontact,&$linesheader) {

        $lines = [];

        $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
        foreach ($ntdurls AS $ntdurl) {
            $record = Input::find($ntdurl->record_id);
            if ($record) {

                scartLog::logLine("D-schedulerSendNTD.getUrlLinesStandard; [$record->filenumber] add standard NTD fields");

                // use actual data
                $lines[$record->url] = [
                    'url' => $record->url,
                    'ntd_note' => $record->ntd_note,
                    'url_ip' => $record->url_ip,
                    'firstseen_at' => $record->firstseen_at,
                    'lastseen_at' => $record->lastseen_at,
                ];
                if (empty($linesheader)) {
                    $linesheader = [
                        'url' => 'url',
                        'ntd_note' => 'ntd_note',
                        'url_ip' => 'url_ip',
                        'firstseen_at' => 'firstseen_at',
                        'lastseen_at' => 'lastseen_at',
                    ];
                }

                if ($abusecontact->police_contact) {
                    // use police reason
                    $lines[$record->url]['ntd_note'] = $record->police_reason;
                }

                // save in ntd_url last actual record info
                $ntdurl->firstseen_at = $record->firstseen_at;
                $ntdurl->lastseen_at= $record->lastseen_at;
                $ntdurl->online_counter = $record->online_counter;
                $ntdurl->ip = $record->url_ip;
                $ntdurl->save();

            } else {
                scartLog::logLine("E-schedulerSendNTD.getUrlLinesStandard; cannot find record from NTD-url; ntdurl=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");
                // skip this one
            }
        }

        return $lines;
    }

    /**
     * Actual sending of the (NTD) messages
     *
     *
     * @param $ntdmsgs
     * @param $status_timestamp
     * @return array
     */

    private static function sendGroupedMessages($ntdmsgs,$status_timestamp) {

        // pick wright email template

        $lang = Lang::getLocale();
        $csvlangdir  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang;
        if (!is_dir($csvlangdir)) {
            scartLog::logLine("W-schedulerSendNTD; language directory '$csvlangdir' NOT found; switch back to lang='en'");
            $lang = 'en';
            $csvlangdir  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang;
        }
        scartLog::logLine("D-schedulerSendNTD; language directory: '$csvlangdir' ");

        $ntd_nots = [];

        foreach ($ntdmsgs AS $abuseemail => $ntdmsg) {

            if ($ntdmsg->isLea) {

                // Webform flow -> LEA contact -> use Webform fields

                if ($ntdmsg->csv_attachment) {

                    /**
                     * LEA attachements -> forward received webform file(s)
                     */

                    // check and add file(s)
                    $tmpfile = [];
                    foreach ($ntdmsg->lines AS $key => $line) {
                        if (!empty($line['hash'])) {
                            $cache = Scrape_cache::getCache($line['hash']);
                            if ($cache) {
                                scartLog::logLine("D-schedulerSendNTD; add image with hash={$line['hash']}");
                                $arr = explode(',',$cache->cached);
                                //scartLog::logLine("D-schedulerSendNTD; arr[0]={$arr[0]}");
                                if (count($arr) > 1) {
                                    $tmpdata = base64_decode($arr[1]);
                                    $ext = 'jpg';
                                    foreach (scartBrowser::$_imageMimeTypes as $mimeType) {
                                        if (strpos($arr[0],$mimeType)!==false) {
                                            $arrm = explode('/',$mimeType);
                                            $ext = ($arrm[1])??$ext;
                                            break;
                                        }
                                    }
                                    $tmp = temp_path() . '/'.$ntdmsg->filenumber.'-'.$line['hash'].'.'.$ext;
                                    file_put_contents($tmp, $tmpdata);
                                    $tmpfile[] = $tmp;
                                }
                                scartLog::logLine("D-schedulerSendNTD; tmp=$tmp, data size=".strlen($tmpdata));
                            } else {
                                scartLog::logLine("W-schedulerSendNTD; image with hash={$line['hash']} not found");
                            }
                            // remove, not needed anymore
                            unset($ntdmsg->lines[$key]);
                        }
                    }
                    // remove HASH column also
                    unset($ntdmsg->webformheaders['hash']);

                    $csvtemp  = $csvlangdir . '/ntdbody-lea.tpl';
                    $abuselinks = self::getTwigParsedLines($ntdmsg,$csvtemp);

                    scartLog::logLine("D-schedulerSendNTD; send (LEA) NTD $ntdmsg->filenumber to '$abuseemail' with attachment(s) ");

                } else {

                    $csvtemp  = $csvlangdir . '/ntdbody-lea.tpl';
                    $abuselinks = self::getTwigParsedLines($ntdmsg,$csvtemp);

                    $tmpfile = '';
                    scartLog::logLine("D-schedulerSendNTD; send (LEA) NTD $ntdmsg->filenumber to '$abuseemail' with urls included in body ");

                }

            } else {

                // @ToDo: change bracket also into Twig()

                if ($ntdmsg->csv_attachment) {

                    //$csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
                    $csvtemp = $csvlangdir . (($ntdmsg->isPolice) ? '/ntdcsvfile-police.tpl' : (($ntdmsg->add_only_url) ? '/ntdcsvfile-onlyurl.tpl' : '/ntdcsvfile.tpl' ));
                    $tmpdata = Bracket::parse(file_get_contents($csvtemp),['lines' => $ntdmsg->lines]);
                    //$tmpdata = self::getParsedLines($ntdmsg,$csvtemp);

                    //scartLog::logDump("D-schedulerSendNTD; parsed tmpdata=",$tmpdata);
                    $tmpfile = temp_path() . '/urls-'.$ntdmsg->filenumber.'.csv';
                    file_put_contents($tmpfile, $tmpdata);

                    $abuselinks = '(abuse urls in CSV attachment)';
                    scartLog::logLine("D-schedulerSendNTD; send NTD $ntdmsg->filenumber to '$abuseemail' with attachment '$tmpfile' ");

                } else {

                    //$csvtemp  = plugins_path() . '/abuseio/scart/views/mailparts/'.$lang.'/';
                    $csvtemp = $csvlangdir . (($ntdmsg->isPolice) ? '/ntdbody-police.tpl' : (($ntdmsg->add_only_url) ? '/ntdbody-onlyurl.tpl' : '/ntdbody.tpl' ));
                    $tmpfile = '';

                    $abuselinks = Bracket::parse(file_get_contents($csvtemp),['lines' => $ntdmsg->lines]);
                    scartLog::logLine("D-schedulerSendNTD; send NTD $ntdmsg->filenumber to '$abuseemail' with urls included in body ");

                }

            }

            // Note: special replacement because strange behaveour detected with old body definitions with html tags

            //scartLog::logDump("D-schedulerSendNTD; abuselinks=",$abuselinks);
            $msg_body = str_replace(['<p>{{'.'abuselinks'.'}}</p>','{abuselinks}'], $abuselinks, $ntdmsg->msg_body);

            $bcc_email = Systemconfig::get('abuseio.scart::scheduler.sendntd.bcc_email','');
            if ($bcc_email) scartLog::logLine("D-schedulerSendNTD; send BCC to '$bcc_email'");

            // if LEA then force sending
            if (Systemconfig::get('abuseio.scart::options.lea_function',false) && $ntdmsg->isLea)  {
                // only when LEA option active and LEA message
                scartLog::logLine("D-schedulerSendNTD; send (LEA) - force sending ");
                $message = scartMail::sendNTD($abuseemail,$ntdmsg->msg_subject,$msg_body,$bcc_email,$tmpfile,$ntdmsg->isLea);
            } else {
                // always force=FALSE
                $message = scartMail::sendNTD($abuseemail,$ntdmsg->msg_subject,$msg_body,$bcc_email,$tmpfile);
            }

            // when sendNTD failed (message=false), mark all related NTD are set failed
            foreach ($ntdmsg->ntd_ids AS $ind => $ntd_id) {

                $ntd = Ntd::find($ntd_id);
                $ntd->msg_subject = $ntdmsg->msg_subject;
                $ntd->msg_body = $msg_body;
                $ntd->msg_ident = ($message) ? $message['id'] : '';
                $ntd->msg_queued = date('Y-m-d H:i:s');

                if (!$message) {

                    scartLog::logLine("W-schedulerSendNTD; cannot deliver to queue; got NO message state");

                    $ntd->status_code = SCART_NTD_STATUS_SENT_FAILED;
                    $ntd->status_time = date('Y-m-d H:i:s');

                    $ntd_not = [
                        'filenumber' => $ntd->filenumber,
                        'status' => $status_timestamp . $ntd->status_code . " cannot deliver to (local) message queue (no message id)",
                        'abusecontact' => "email address: $abuseemail",
                    ];
                    $ntd_nots[] = $ntd_not;

                    $ntd->logText("NTD sent by email failed; status=".$ntd->status_code);
                } else {
                    $ntd->logText("NTD sent by email; status=".$ntd->status_code);
                }

                $ntd->save();

                if ($ind > 0) {
                    $ntd->logText("NTD merged with $ntdmsg->filenumber and msg-id '$ntd->msg_ident' at $ntd->msg_queued for $abuseemail");
                } else {
                    $ntd->logText("NTD filled and queued with msg-id '$ntd->msg_ident' at $ntd->msg_queued for $abuseemail");
                }

            }

            if ($tmpfile) {
                if (!is_array($tmpfile)) $tmpfile = [$tmpfile];
                foreach ($tmpfile as $tmp) {
                    @unlink($tmp);
                }
            }

        }

        return $ntd_nots;
    }

    private static function getTwigParsedLines($ntdmsg,$csvtemp) {

        $headers = array_keys($ntdmsg->webformheaders);

        try {

//            $twig = new Twig();
//            $tmpdata = $twig->parse(file_get_contents($csvtemp),[
//                'headers' => $headers,
//                'lines' => $ntdmsg->lines,
//            ]);

            scartLog::logLine("D-schedulerSendNTD; getTwigParsedLines render with twig without cache");

            // disable CACHING
            $loader = new ArrayLoader([
                'template' => file_get_contents($csvtemp),
            ]);
            $twig = new Environment($loader, [
                'cache' => false,
                'auto_reload' => true,
            ]);
            $tmpdata = $twig->render('template', [
                'headers' => $headers,
                'lines' => $ntdmsg->lines,
            ]);

        } catch (\Exception $err) {
            scartLog::logLine("E-schedulerSendNTD; getTwigParsedLines error: ".$err->getMessage());
        }

//        $tmpdata = '';
//        foreach ($headers as $header) {
//            $tmpdata .= $header.';';
//        }
//        $tmpdata .= "\n";
//        foreach ($ntdmsg->lines as $line) {
//            foreach ($headers as $header) {
//                $tmpdata .= $line[$header].';';
//            }
//            $tmpdata .= "\n";
//        }

        return $tmpdata;
    }

    private static function validateWhois($ntd,$abuseemail) {

        scartLog::logLine("D-schedulerSendNTD; validateWhois for ntd_id=$ntd->id, abuseemail=$abuseemail ");

        // collect domains for optimalization of verify/whois/proxyAPI calls
        $proxy_update_domains = $notchanged_domains = [];

        $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
        foreach ($ntdurls AS $ntdurl) {
            $record = Input::find($ntdurl->record_id);
            if ($record) {

                // Note: double check here if record has checkonline status? To be sure?

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

                // Note: check domain only once if not changed

                if (!in_array($domain,$notchanged_domains)) {

                    // verify WhoIs -> force no database cache
                    $whois = scartWhois::verifyWhoIs($record,true,false);

                    if ($whois['status_success']) {

                        // CHECK IF CHANGED HOSTER

                        if ($whois[SCART_HOSTER . '_changed']) {

                            scartLog::logLine("D-schedulerSendNTD; WhoIs changed for ntdurl_id=$ntdurl->id, record_type=$ntdurl->record_type, record_id=$ntdurl->record_id");

                            // log change of hoster
                            $record->logText($whois[SCART_HOSTER . '_changed_logtext']);

                            $status = (($whois[SCART_HOSTER . '_changed_logtext']!='') ? $whois[SCART_HOSTER . '_changed_logtext'] : '');
                            $status = "Stop sending NTD - check analyst (CHANGED) - $status";
                            scartLog::logLine("D-$status");

                            // be sure this url is removed from (any) NTD
                            Ntd::removeUrlgrouping($record->url);

                            // log old/new for history
                            $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,"Detected hoster change for url within NTD");

                            // set waiting for analist
                            $record->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;

                            $record->logText($status);
                            $record->logText("Set status_code on: " . $record->status_code);

                        } else {
                            $notchanged_domains[$domain] = $domain;
                        }

                        // always save because of possible changes in scartWhois::verifyWhoIs
                        $record->save();

                    }  // ignore

                } else {
                    //scartLog::logLine("D-schedulerSendNTD; hoster of '$domain' not  changed - skip verify");
                }

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

                $status_timestamp = '['.date('Y-m-d H:i:s').'] ';

                /**
                 * EXIM is a mailing system with mailsecurity checks (tls, dkim, eg) build in
                 * This can be used to be sure the email is sent secure
                 * When SCHEDULER_NTDSEND_MAILLOGFILE is left empty, this check is skipped
                 */

                // note: message-id is not always supported
                if ($ntd->msg_ident!='') {
                    // check status -> scartEXIM logt
                    $status = scartEXIM::getMTAstatus($ntd->msg_ident);
                } else {
                    scartLog::logLine("D-getMTAstatus: message_id not supported - skip check MTA status");
                    $status = SCART_NTD_STATUS_SENT_SUCCES;
                }

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
