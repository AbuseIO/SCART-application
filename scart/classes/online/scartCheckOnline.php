<?php
namespace abuseio\scart\classes\online;

use Db;
use abuseio\scart\classes\helpers\scartUsers;
use Config;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Whois;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\models\Input_verify;

class scartCheckOnline {

    /**
     * Checkonline
     *
     * Comes here when:
     * a. illegal
     * b. first time
     * c. lastseen (last check) is to long ago
     *
     * Note:
     * - may be optimize when input get_images also get image from illegal linked image
     *
     * @param $input
     * @param $check_online_every
     * @param $registrar_interval
     * @return array
     */

    public static function doCheckIllegalOnline($record,$countrecs,$current,$taskname='') {

        $job_records = [];

        try {

            // init config and state vars
            self::setLock($record->id,true);
            $registrar_active = Systemconfig::get('abuseio.scart::ntd.registrar_active',true);        // if NTD to registrar
            $siteowner_interval = Systemconfig::get('abuseio.scart::ntd.siteowner_interval',3);       // 3x times hoster
            $registrar_interval = Systemconfig::get('abuseio.scart::ntd.registrar_interval',6);       // 6x times hoster
            $status_timestamp = '[time: '.date('Y-m-d H:i:s')."; record: {$current}/{$countrecs}] ";

            if ($record->status_code==SCART_STATUS_FIRST_POLICE) {

                // seperated flow (eg no verifyWhoIs)

                if ($record->online_counter==0) {

                    // create one DIRECTLY -> sent direct to POLICE abusecontact

                    scartLog::logLine("D-scartCheckOnline; first time for illegal content (id=$record->id); (record_type=".class_basename($record).") setup NTD for status_code=$record->status_code");

                    // find police contact
                    $abusecontact_id = Abusecontact::findPolice();

                    if ($abusecontact_id) {
                        $ntdstat = SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE;
                        scartLog::logLine("D-scartCheckOnline; create $ntdstat");
                        $ntd = Ntd::createNTDurl($abusecontact_id, $record, $ntdstat, 1, SCART_NTD_ABUSECONTACT_TYPE_POLICE);

                        $status = "Added to message for informing POLICE " ;

                        if (scartICCAMinterface::isActive()) {

                            // Note: reference can be empty because record is not yet reported to ICCAM

                            // ICCAM set SCART_ICCAM_ACTION_LEA
                            scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION, [
                                'record_type' => class_basename($record),
                                'record_id' => $record->id,
                                'object_id' => $record->reference,          // can be empty
                                'action_id' => SCART_ICCAM_ACTION_LEA,
                                'country' => '',                            // hotline default
                                'reason' => 'SCART reported to LEA',
                            ]);

                        }

                        $record->logText('Sent to police');

                    } else {
                        scartLog::logLine("E-scartCheckOnline; NO POLICE ABUSECONTACT SET!? ");

                        $status = "CAN NOT FIND POLICE ABUSECONTACT - MUST BE SET!? " ;

                    }

                    // update
                    $record->online_counter += 1;
                    $record->lastseen_at = date('Y-m-d H:i:s');
                    $record->save();

                    $job_records[] = [
                        'filenumber' => $record->filenumber,
                        'url' => $record->url,
                        'status' => $status_timestamp . $status,
                    ];

                } else {

                    // do nothing -> wait until other status

                }

            } else {

                scartLog::logLine("D-scartCheckOnline; check (whois/rules/online) id=$record->id, lastseen=$record->lastseen_at, received_at=$record->received_at ");

                if ($record->online_counter==0) {
                    $verify_active = Systemconfig::get('abuseio.scart::verify.active',false);
                    if ($verify_active) {
                        // First time and verify active so import for multiple verification
                        Input_verify::import($record);
                    }
                }

                // start positive
                $update_record = true;

                // calculate lead time
                $startTime = microtime(true);

                /**
                 * 1: VERIFYWHOIS
                 *    -> if not found or errors or changed -> stop
                 *    -> else cont
                 * 2: DIRECT_CLASSIFY
                 *    -> doDirectNTD -> add first time to NTD
                 * 3: GETIMAGES
                 *    -> if offline -> stop after retries
                 *    -> if online doGroupingNTD
                 *
                 */

                $whois = scartWhois::verifyWhoIs($record);

                $whoisleadtime = microtime(true);
                scartLog::logLine("D-scartCheckOnline; [$record->filenumber] verifyWhoIs lead time: " . ($whoisleadtime - $startTime) );

                if ($whois['status_success']) {

                    // reset first if set (eg last time whois error )
                    if ($record->whois_error_retry > 0) {
                        $record->whois_error_retry = 0;
                    }

                    // CHECK IF CHANGED ABUSECONTACT

                    if (($registrar_active && $whois[SCART_REGISTRAR.'_changed']) || $whois[SCART_HOSTER.'_changed']) {

                        // log change of abusecontact(s)
                        if ($whois[SCART_REGISTRAR.'_changed']) $record->logText($whois[SCART_REGISTRAR.'_changed_logtext']);
                        if ($whois[SCART_HOSTER.'_changed']) $record->logText($whois[SCART_HOSTER.'_changed_logtext']);

                        $status  = '';
                        $status .= ($whois[SCART_REGISTRAR.'_changed_logtext']) ? $whois[SCART_REGISTRAR.'_changed_logtext'] : '';
                        $status .= ($status) ? ', ' : '';
                        $status .= ($whois[SCART_HOSTER.'_changed_logtext']) ? $whois[SCART_HOSTER.'_changed_logtext'] : '';
                        $status = "Stop checkonline - wait for analist (CHANGED) - $status";
                        scartLog::logLine("D-[$record->filenumber] $status");

                        $job_records[] = [
                            'filenumber' => $record->filenumber,
                            'url' => $record->url,
                            'status' => $status_timestamp . $status,
                        ];

                        // be sure this url is removed from (any) NTD
                        Ntd::removeUrlgrouping($record->url);

                        // log old/new for history
                        $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,"Checkonline; hoster is changed");

                        // set waiting for analist
                        $record->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;
                        $record->logText($status);
                        $record->logText("Set status_code on: " . $record->status_code);

                    } else {

                        /**
                         * Check here if hoster in other country
                         * Then stop looking up image(s) and set moved-to-other-country
                         * In this way image processing is not needed, gives overhead
                         */

                        // get abusecontact information
                        $abusecontact = Abusecontact::find($record->host_abusecontact_id);
                        $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
                        $abusecountry = ($abusecontact) ? $abusecontact->abusecountry : $hotlinecountry;

                        if (!scartGrade::isLocal($abusecountry)) {

                            $record = self::handleAbusecontactNotOkay($record,$status_timestamp,$job_records);

                            // continue WITHOUT futher image lookup/handling
                            scartLog::logLine("W-scartCheckOnline; [$record->filenumber] skip image lookup, hosting not in local country (country=$abusecountry) ");

                        } else {

                            // if DIRECT_CLASSIFY AND MANUAL=Y then NO CHECKONLINE (=ALWAYS ONLINE)
                            $directntd = $online = false;

                            // 2: DIRECT_CLASSIFY_ILLEGAL -> always add (one time) to NTD

                            if ($settings = scartRules::checkDirectClassify($record)) {

                                if ($settings['rule_type_code'] == SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL) {

                                    // direct_classify
                                    scartLog::logLine("D-Direct_classify rule active for '$record->url' ");

                                    // mark direct classify
                                    $directntd = true;

                                    // check checkonline_MANUAL setting
                                    $manual = (isset($settings['checkonline_manual'][0])) ? $settings['checkonline_manual'][0] : '';
                                    if ($manual == 'y' && $record->status_code != SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) {

                                        // log old/new for history
                                        $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,"Checkonline; direct classify rule; set check manual");

                                        // set on SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL
                                        $record->status_code = SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL;
                                        $record->logText("DIRECT_CLASSIFY and option checkonline_manual is SET; set status on $record->status_code");
                                    }

                                }

                            }

                            // 2020/9/10/gS: if CHECKONLINE_MANUAL then no checkonline
                            $nocheckonline = ($record->status_code == SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL);

                            // 3: GET IMAGES (CHECK IF ONLINE)

                            if ($nocheckonline) {

                                // always
                                $online = true;

                                // fill
                                $imageleadtime = microtime(true);
                                //scartLog::logLine("D-scartCheckOnline; [$record->filenumber] DO NOT getImages; lead time: " . ($imageleadtime - $whoisleadtime) );


                            } else {

                                // Check if urlcheckonline active for this record

                                if ( ($addon = scartRules::linkCheckerOnline($record->url)) ) {

                                    // what if link checker (tmp) offline? -> then result is offline and then there will be a retry (like image browser is offline)

                                    $online = Addon::run($addon,$record);
                                    $imageleadtime = microtime(true);
                                    scartLog::logLine("D-scartCheckOnline; [$record->filenumber] Addon($addon->codename)::run()=$online; lead time: " . ($imageleadtime - $whoisleadtime) );

                                } else {

                                    // NO CACHE (!)
                                    scartBrowser::setCached(false);

                                    // if website content then take only screenshot -> optimize scraping
                                    scartLog::logLine("D-scartCheckOnline; [$record->filenumber] start getImages...");
                                    $images = scartBrowser::getImages($record->url, $record->url_referer,true,true);

                                    // check if error browser
                                    if ($browsererror = scartBrowser::getLasterror()) {

                                        scartLog::logLine("W-scartCheckOnline; [$record->filenumber, $record->url] browser error=$browsererror - server/netwerk/image down");

                                        $error = [
                                            "browser error: " . $browsererror,
                                            'task name: '.$taskname,
                                            'filenumber: ' . $record->filenumber,
                                            'host: ' . $record->url_host,
                                        ];
                                        scartAlerts::alertAdminStatus('BROWSER_ERROR','scartCheckOnline', true, $error, 3, 12);

                                        // force no images
                                        $images = [];

                                    } else {

                                        scartAlerts::alertAdminStatus('BROWSER_ERROR','scartCheckOnline', false);

                                    }

                                    // admin report warning

                                    $imgcnt = ($images) ? count($images) : 0;
                                    // do not log each time
                                    //$input->logText("Input (link) $input->url CHECKONLINE; found $imgcnt image" . (($imgcnt > 1) ? 's' : ''));

                                    $imageleadtime = microtime(true);
                                    scartLog::logLine("D-scartCheckOnline; [$record->filenumber] image count: $imgcnt; getImages lead time: " . ($imageleadtime - $whoisleadtime) );

                                    $image = [];

                                    if ($imgcnt > 0) {

                                        reset($images);
                                        $image = current($images);

                                        if ($record->url_type == SCART_URL_TYPE_MAINURL  ) {

                                            /**
                                             * Different results from getImages depending on type of url; website or direct image/video
                                             * The first image holds this information; when website then screenshot, else the image
                                             *
                                             */

                                            $imagetype = $image['type'] ?? SCART_URL_TYPE_IMAGEURL;
                                            scartLog::logLine("D-scartCheckOnline; [$record->filenumber] image content: $imagetype ");

                                            if ($imagetype == SCART_URL_TYPE_SCREENSHOT) {

                                                // Note: we don't hash the screenshot for checking differences because of dynamic/time/date values on website
                                                $online = true;

                                            } else  {

                                                $online = ($record->url_hash == $image['hash']);
                                                if (!$online) {
                                                    scartLog::logLine("W-scartCheckOnline; [$record->filenumber] mainurl '$record->url' hash check FALSE; url_hash=$record->url_hash <> image hash=" . $image['hash'] );
                                                }

                                            }

                                        } elseif ($record->url_type == SCART_URL_TYPE_VIDEOURL || $record->url_type == SCART_URL_TYPE_IMAGEURL) {

                                            // first image is image/video
                                            $online = ($record->url_hash == $image['hash']);
                                            if (!$online) {
                                                scartLog::logLine("W-scartCheckOnline; [$record->filenumber] image/video url '$record->url' hash check FALSE; url_hash=$record->url_hash <> image hash=" . $image['hash'] );
                                            }

                                        } else {

                                            // IGNORE
                                            scartLog::logLine("W-scartCheckOnline; [$record->filenumber] unknown record url_type '$record->url_type' ");

                                        }

                                    } else {

                                        // no image(s) / not found (anymore)
                                        $online = false;
                                    }

                                }

                            }

                            if ($online) {

                                // ** RECORD IS (STILL) ONLINE **

                                // check if first time here
                                if ($record->online_counter == 0) {

                                    if ($directntd) {

                                        // if SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL then one time NTD here

                                        // create DIRECT NTD(s)
                                        $record = SELF::doDirectNTD($record,$status_timestamp,$siteowner_interval,$registrar_active,$registrar_interval,$job_records);

                                        $status = "DIRECT_CLASSIFY rule; FIRST TIME in checkonline AND online; add url to NTD ";
                                        $status .= (($nocheckonline) ? '(type='.SCART_TYPE_CODE_IMAGEHOSTER.'; no check online)' : '(before check online)' ) ;
                                        $record->logText($status);

                                        // report
                                        $job_records[] = [
                                            'filenumber' => $record->filenumber,
                                            'url' => $record->url,
                                            'status' => $status_timestamp . $status,
                                        ];

                                    } else {

                                        if (!$nocheckonline) {
                                            scartLog::logLine("D-scartCheckOnline; [$record->filenumber] first time in checkonline ");
                                        }

                                    }

                                }

                                if ($nocheckonline) {
                                    scartLog::logLine("D-scartCheckOnline; [$record->filenumber] manual check, NO check online ");
                                } else {
                                    scartLog::logLine("D-scartCheckOnline; [$record->filenumber] still online");
                                }

                                // 2020/5/14/Gs: added support of HASH server check

                                $record->hashcheck_at = date('Y-m-d H:i:s');
                                // Note: if HASH check off, then empty
                                $record->hashcheck_format = scartHASHcheck::getFormat();

                                $hashleadtime = microtime(true);
                                scartLog::logLine("D-scartCheckOnline; [$record->filenumber] scartHASHcheck lead time: " . ($hashleadtime - $imageleadtime) );

                                if (isset($image['data'])) {
                                    // Note: if HASH check off, then always false
                                    $record->hashcheck_return = scartHASHcheck::inDatabase($image['data']);
                                } else {
                                    $record->hashcheck_return = false;
                                }

                                if ($record->hashcheck_return) {

                                    // Found in HASH database
                                    scartLog::logLine("D-scartCheckOnline; found in HASH database; filenumber=$record->filenumber ");

                                    $status = "Warning; url (still) found in HASH database ";
                                    $record->logText($status);

                                    // report
                                    $job_records[] = [
                                        'filenumber' => $record->filenumber,
                                        'url' => $record->url,
                                        'status' => $status_timestamp . $status,
                                    ];

                                }

                                // reset first if set (eg browser error or maintenance webpage )
                                if ($record->browse_error_retry > 0) {
                                    $record->browse_error_retry = 0;
                                }

                                // if $record->online_counter==0 then SCART_NTD_STATUS_QUEUE_DIRECTLY

                                if ($record->online_counter == 0) {
                                    // skip if already done for direct_classify
                                    if (!$directntd) {
                                        // create DIRECT NTD(s)
                                        $record = SELF::doDirectNTD($record,$status_timestamp,$siteowner_interval,$registrar_active,$registrar_interval,$job_records);
                                    }
                                } else {
                                    // create grouping NTD(s)
                                    $record = SELF::doGroupingNTD($record,$status_timestamp,$siteowner_interval,$registrar_active,$registrar_interval);
                                }




                                // IF SCART_STATUS_ABUSECONTACT_CHANGED then no NTD is sent and we will be back here, when abusecontact is approved

                                if ($record->status_code != SCART_STATUS_ABUSECONTACT_CHANGED) {
                                    $record->online_counter += 1;
                                    $record->checkonline_leadtime = microtime(true) - $startTime;
                                    scartLog::logLine("D-scartCheckOnline; [$record->filenumber] total checkonline lead time: $record->checkonline_leadtime");
                                    $record->lastseen_at = date('Y-m-d H:i:s');
                                }

                            } else {

                                // check SCART_BROWSE_ERROR_MAX to be sure it's not a network problem

                                // save the leadtime
                                $record->checkonline_leadtime = microtime(true) - $startTime;
                                // retry + 1
                                $record->browse_error_retry += 1;
                                scartLog::logLine("D-scartCheckOnline; [$record->filenumber] OFFLINE (retry=$record->browse_error_retry); offline lead time: " . $record->checkonline_leadtime);

                                if ($record->browse_error_retry >= SCART_BROWSE_ERROR_MAX) {

                                    // OFFLINE -> mark input (images) offline

                                    scartLog::logLine("D-scartCheckOnline; offline retry max reached (=$record->browse_error_retry), lastseen_at=$record->lastseen_at, received_at=$record->received_at ");
                                    $record->logText("offline retry max reached");

                                    if ($directntd) {
                                        scartLog::logLine("D-scartCheckOnline;[$record->filenumber]; DIRECT_CLASSIFY; skip remove from (first) NTD");
                                    } else {
                                        // be sure this url is removed from (any) NTD
                                        Ntd::removeUrlgrouping($record->url);
                                        $record->logText("Removed from any (grouping) NTD's");
                                    }

                                    // log old/new for history
                                    $new = ($record->status_code==SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) ? SCART_STATUS_CLOSE_OFFLINE_MANUAL : SCART_STATUS_CLOSE_OFFLINE;
                                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$new,"Checkonline; found url offline");
                                    $record->status_code = $new;
                                    $record->logText("Set status_code on: " . $record->status_code);

                                    $status = "Stop checkonline - OFFLINE or HASH changed";
                                    scartLog::logLine("D-$status");

                                    $job_records[] = [
                                        'filenumber' => $record->filenumber,
                                        'url' => $record->url,
                                        'status' => $status_timestamp . $status,
                                    ];

                                    if (scartICCAMinterface::isActive()) {

                                        // Note: reference can be empty because record is not yet reported to ICCAM

                                        // ICCAM content removed
                                        scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                            'record_type' => class_basename($record),
                                            'record_id' => $record->id,
                                            'object_id' => $record->reference,
                                            'action_id' => SCART_ICCAM_ACTION_CR,
                                            'country' => '',                                // hotline default
                                            'reason' => 'SCART found content removed',
                                        ]);

                                    }

                                }

                            }

                        }

                    }

                } else {

                    /**
                     * IP/domain lookup error
                     *
                     * Retry (SCART_WHOIS_ERROR_MAX) times; interface can be temp down
                     *
                     */

                    $record->whois_error_retry += 1;

                    scartLog::logLine("D-scartCheckOnline; no whois info?; '$record->url'  whois_error_retry=$record->whois_error_retry");

                    if ($record->whois_error_retry >= SCART_WHOIS_ERROR_MAX) {

                        scartLog::logLine("D-scartCheckOnline; WHOIS check retry max reached (=$record->whois_error_retry), lastseen_at=" . $record->lastseen_at);
                        $record->logText("WHOIS CHECK retry max reached (=$record->whois_error_retry)");

                        $status = "Stop checkonline - NO WHOIS INFO FOUND - wait for analist (CHANGED)";
                        $record->logText($status);

                        $job_records[] = [
                            'filenumber' => $record->filenumber,
                            'url' => $record->url,
                            'status' => $status_timestamp . $status,
                        ];

                        // be sure this url is removed from (any) NTD
                        Ntd::removeUrlgrouping($record->url);

                        // log old/new for history
                        $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,"Checkonline; cannot find WhoIs information");

                        // set waiting for analist
                        $record->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;
                        $record->logText("Set status_code on: " . $record->status_code);

                    }

                }

                // update if set
                if ($update_record) {
                    // save changed browse_error_retry and/or status_code
                    $record->save();
                }

            }

        } catch (\Exception $err) {

            scartLog::logLine("E-scartCheckOnline; exception '" . $err->getMessage() . "', at line " . $err->getLine());

        }

        // reset lock -> direct update, NO SAVE() here -> record can be in an inconsistant state after error
        self::setLock($record->id,false);

        return $job_records;
    }


    static function doDirectNTD($record,$status_timestamp,$siteowner_interval,$registrar_active,$registrar_interval,&$job_records) {

        $ntdstat = SCART_NTD_STATUS_QUEUE_DIRECTLY;
        scartLog::logLine("D-scartCheckOnline; [$record->filenumber] create $ntdstat for hoster");
        $ntd = Ntd::createNTDurl($record->host_abusecontact_id,$record,$ntdstat, 1, SCART_NTD_ABUSECONTACT_TYPE_HOSTER);
        if ($ntd) {

            // if NTD created, then valid

            if (scartICCAMinterface::isActive()) {

                // Note: reference can be empty because record is not yet reported to ICCAM

                // ICCAM set SCART_ICCAM_ACTION_ISP
                scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                    'record_type' => class_basename($record),
                    'record_id' => $record->id,
                    'object_id' => $record->reference,
                    'action_id' => SCART_ICCAM_ACTION_ISP,
                    'country' => '',                            // hotline default
                    'reason' => 'SCART reported to ISP',
                ]);

            }

            // check if site_owner rule -> send also NTD

            $siteowneradded = '';
            if ($siteownerac_id = scartRules::checkSiteOwnerRule($record->url) ) {
                $siteownerac = Abusecontact::find($siteownerac_id);
                if ($siteownerac) {
                    scartLog::logLine("D-scartCheckOnline; create hoster NTD (DIRECT + GROUPING) also for site_owner: $siteownerac->owner ");
                    $ntd = Ntd::createNTDurl($siteownerac_id, $record, SCART_NTD_STATUS_QUEUE_DIRECTLY, 1, SCART_NTD_ABUSECONTACT_TYPE_SITEOWNER);
                    if ($ntd) {
                        // create one grouping -> sent after (sitewoner_interval)) x hoster_hours when still online
                        $ntd = Ntd::createNTDurl($siteownerac_id, $record,SCART_NTD_STATUS_GROUPING, $siteowner_interval, SCART_NTD_ABUSECONTACT_TYPE_SITEOWNER);
                        $siteowneradded = 'and site owner';
                    }
                } else {
                    scartLog::logLine("W-scartCheckOnline; can not find siteownerac_id=$siteownerac_id ");
                }
            }

            // 2020/7/27/Gs: IMAGE(S) and VIDEO(s)

            $status = "Added to NTD for informing hoster $siteowneradded" ;
            $job_records[] = [
                'filenumber' => $record->filenumber,
                'url' => $record->url,
                'status' => $status_timestamp . $status,
            ];

            // create one grouping -> sent when still online
            $ntd = Ntd::createNTDurl($record->host_abusecontact_id,  $record, SCART_NTD_STATUS_GROUPING, 1, SCART_NTD_ABUSECONTACT_TYPE_HOSTER);

            if ($registrar_active) {

                // REGISTRAR -> create NTD each registar_interval

                //scartLog::logLine("D-scartCheckOnline; create NTD for registrar (registrar_interval=$registrar_interval)");
                $ntd = Ntd::createNTDurl($record->registrar_abusecontact_id, $record, SCART_NTD_STATUS_GROUPING, $registrar_interval, SCART_NTD_ABUSECONTACT_TYPE_REGISTRAR);

            }

        } else  {

            // NO NTD created

            // be sure this url is removed from (any) NTD
            Ntd::removeUrlgrouping($record->url);

            // handle abusecontact not okay
            $record = self::handleAbusecontactNotOkay($record,$status_timestamp,$job_records);

        }

        return $record;
    }

    static function handleAbusecontactNotOkay($record,$status_timestamp,&$job_records) {

        // get abusecontact information
        $abusecontact = Abusecontact::find($record->host_abusecontact_id);
        $abuseowner = ($abusecontact) ? $abusecontact->owner . " ($abusecontact->filenumber)" : SCART_ABUSECONTACT_OWNER_EMPTY;
        $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
        $abusecountry = ($abusecontact) ? $abusecontact->abusecountry : $hotlinecountry;

        if (empty($abusecontact) || scartGrade::isLocal($abusecountry)) {

            // local, but not approved (yet)

            $status = "Stop check online; wait for analist (CHANGED); unknown hoster or not (yet) GDPR approved; hoster is: $abuseowner" ;
            $job_records[] = [
                'filenumber' => $record->filenumber,
                'url' => $record->url,
                'status' => $status_timestamp . $status,
            ];

            // log old/new for history
            $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,"Checkonline; unknown hoster and/or not (yet) GDPR approved");

            // CHANGED
            $record->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;

        } else {

            $status = "Stop check online; hoster not in local country; hoster is: $abuseowner" ;

            // ICCAM

            if (scartICCAMinterface::isActive()) {

                // ONLY WHEN EXISTING RECORD IN ICCAM -> timing is important

                if ($reportId = scartICCAMinterface::getICCAMreportID($record->reference)) {

                    $contentId = scartICCAMinterface::getICCAMcontentID($record->reference);

                    scartLog::logLine("D-scartCheckOnline; export content moved action for reportId=$reportId, contentId=$contentId");

                    // get hoster counter
                    $reason = 'SCART content moved to country: '.$abusecountry;
                    $status .= "; add action ICCAM moved country '$abusecountry'";

                    // ICCAM content moved (outside NL)
                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                        'record_type' => class_basename($record),
                        'record_id' => $record->id,
                        'object_id' => $record->reference,
                        'action_id' => SCART_ICCAM_ACTION_MO,
                        'country' => $abusecountry,
                        'reason' => $reason,
                    ]);

                }

            }

            $job_records[] = [
                'filenumber' => $record->filenumber,
                'url' => $record->url,
                'status' => $status_timestamp . $status,
            ];

            // log old/new for history
            $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_CLOSE,"Checkonline; hoster not in local country");

            // CLOSE
            $record->status_code = SCART_STATUS_CLOSE;

        }

        $record->logText($status);
        $record->logText("Set status_code on: " . $record->status_code);

        scartLog::logLine("D-$status");

        return $record;
    }


    static function doGroupingNTD($record,$status_timestamp,$siteowner_interval,$registrar_active,$registrar_interval) {

        // HOSTER
        $ntd = Ntd::createNTDurl($record->host_abusecontact_id, $record, SCART_NTD_STATUS_GROUPING, 1, SCART_NTD_ABUSECONTACT_TYPE_HOSTER);
        if ($ntd) {

            $status = "Url still online & WhoIs not changed - url part of grouping NTD" ;
            $job_records[] = [
                'filenumber' => $record->filenumber,
                'url' => $record->url,
                'status' => $status_timestamp . $status,
            ];

            // check if site_owner rule

            if (($siteownerac_id = scartRules::checkSiteOwnerRule($record->url))) {
                $siteownerac = Abusecontact::find($siteownerac_id);
                if ($siteownerac) {
                    $ntd = Ntd::createNTDurl($siteownerac_id, $record,SCART_NTD_STATUS_GROUPING, $siteowner_interval, SCART_NTD_ABUSECONTACT_TYPE_SITEOWNER);
                } else {
                    scartLog::logLine("W-scartCheckOnline; can not find siteownerac_id=$siteownerac_id ");
                }
            }

            if ($registrar_active) {

                // REGISTRAR -> create NTD each registar_interval

                //scartLog::logLine("D-scartCheckOnline; create NTD for registrar (registrar_interval=$registrar_interval)");
                $ntd = Ntd::createNTDurl($record->registrar_abusecontact_id, $record, SCART_NTD_STATUS_GROUPING, $registrar_interval, SCART_NTD_ABUSECONTACT_TYPE_REGISTRAR);

            }

        } else {

            // NO NTD created -> HOSTER not NL and/or GDPR

            $abusecontact = Abusecontact::find($record->host_abusecontact_id);
            $abuseowner = ($abusecontact) ? $abusecontact->owner . " ($abusecontact->filenumber)" : SCART_ABUSECONTACT_OWNER_EMPTY;
            $status = "Stop checkonline - wait for analist (CHANGED) - Abusecontact not in local country and/or GDPR approved; hoster is: $abuseowner";
            scartLog::logLine("D-scartCheckOnline; [$record->filenumber] $status");

            $job_records[] = [
                'filenumber' => $record->filenumber,
                'url' => $record->url,
                'status' => $status_timestamp . $status,
            ];

            // be sure this url is removed from (any) NTD
            Ntd::removeUrlgrouping($record->url);

            // log old/new for history
            $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,"Checkonline; hoster not in local country and/or GDPR approved");

            // set waiting for analist
            $record->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;
            $record->logText($status);
            $record->logText("Set status_code on: " . $record->status_code);

        }

        return $record;
    }

    public static function setLock($id,$set=true) {
        // reset lock -> direct update, NO SAVE() here -> record can be in inconsistant state after error
        scartLog::logLine("D-scartCheckOnline; ".(($set)?'set':'reset')." checkonline lock (id=$id); ");
        Db::table(SCART_INPUT_TABLE)->where('id',$id)->update(['checkonline_lock' => (($set)?date('Y-m-d H:i:s'):null)]);
    }

    public static function resetAllLocks() {
        scartLog::logLine("D-scartCheckOnline; reset all checkonline locks");
        Db::table(SCART_INPUT_TABLE)->whereNotNull('checkonline_lock')->update(['checkonline_lock' => null]);
    }

    public static function checkLocks($before) {
        scartLog::logLine("D-scartCheckOnline; check if checkonline-locks older then '$before'");
        return (Db::table(SCART_INPUT_TABLE)->whereNotNull('checkonline_lock')->where('checkonline_lock','<=',$before)->count());
    }

    public static function getOldLocks($before) {
        scartLog::logLine("D-scartCheckOnline; get records with lock older then '$before'");
        return (Db::table(SCART_INPUT_TABLE)->whereNotNull('checkonline_lock')->where('checkonline_lock','<=',$before)->get());
    }

    public static function resetOldLocks($before) {
        scartLog::logLine("D-scartCheckOnline; check if checkonline-locks not older then '$before'");
        Db::table(SCART_INPUT_TABLE)->whereNotNull('checkonline_lock')->where('checkonline_lock','<=',$before)->update(['checkonline_lock' => null]);
    }

}
