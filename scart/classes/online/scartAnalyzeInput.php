<?php

/**
 * Main analyze functions
 *
 * 2019/8/12/Gs:
 * - converted to holding whois within abusecontact table
 * - check if whois info already in abusecontact based on owner name and/or aliases
 * - if not, automatic add an new abusecontact with the whois info
 *
 */

namespace abuseio\scart\classes\online;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use BackendMenu;
use BackendAuth;
use League\Flysystem\Exception;
use Config;
use Log;
use Mail;
use Validator;
use abuseio\scart\models\Systemconfig;

use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\base\scartModel;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_template;
use abuseio\scart\models\Ntd_url;
use abuseio\scart\models\Whois;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\online\scartHASHcheck;

class scartAnalyzeInput {

    /**
     * getHostingInfo
     *
     * Return
     * - registrar owner and abusecontact
     * - domain owner and abusecontact
     * - custome abuse contact (if found)
     *
     * @param $link url
     * @return $whois array with fiels
     */

    static $_whoisfields = [
        'registrar_owner',                // registrar owner name
        'registrar_country',              // registrar country
        'registrar_abusecontact',         // registrar abuse contact
        'host_owner',                     // host owner
        'host_country',                   // host country
        'host_abusecontact',              // host abuse contact
    ];

    // absolute
    public static function getWhoisFields() {
        return self::$_whoisfields;
    }

    /**
     * Analyze input
     *
     * 1: get hostinginfo url
     * 2: get images from url
     * 3: for each image
     *   a: images size, hash
     *   b: hostinginfo image link
     *   c: make item
     *
     * - $input->logText(); functional logging
     * - scartLog::logLine(); technical logging
     *
     * @param $input
     * @return result [status,warning,notcnt]
     *
     */

    public static function doAnalyze($input) {

        $input->logText( 'Analyze input: '.$input->url);

        $result = ''; $stat_new = $stat_upd = $stat_skip = $donecnt = $imgcnt = $delivered_items = 0;

        // check if scrapped before
        $itemscount = Input_parent::where('parent_id',$input->id)->count();
        if ($itemscount > 0) {

            scartLog::logLine("D-doAnalyze; mark removed items; count=$itemscount " );

            // remove connection(s)
            Input_parent::where('parent_id',$input->id)->delete();

            // To-Do:
            //
            // what if imageurl on status_code=grade and is not anymore found in the scrape below
            // -> then this imageurl (record) is Orphan
            // -> but because of some parent (mainurl) before this parent, this orphan is not closed


        }

        // Check double url

        $oldies = Input::where('url',$input->url)
            ->where('url_type',SCART_URL_TYPE_MAINURL)
            ->where('id','<>',$input->id)
            ->where('received_at','>=',$input->received_at)
            ->where('status_code','<>',SCART_STATUS_CLOSE_DOUBLE)
            ->get();
        if ($oldies) {
            foreach ($oldies as $oldie) {
                scartLog::logLine("E-Found record_id=$oldie->id received_at=$oldie->received_at with same url=$oldie->url and status_code=$oldie->status_code -> set on ".SCART_STATUS_CLOSE_DOUBLE);

                // log old/new for history
                $oldie->logHistory(SCART_INPUT_HISTORY_STATUS,$oldie->status_code,SCART_STATUS_CLOSE_DOUBLE,"Detected as double url");

                $oldie->status_code = SCART_STATUS_CLOSE_DOUBLE;
                $oldie->save();
            }
        }

        // Start flow

        if (scartRules::doNotScrape($input->url)) {

            $input->logText("mainurl '$input->url' in DO NOT SCRAPE rule - SKIP" );

            $result = [
                'status' => false,
                'warning' => "Mainurl '$input->url' in DO-NOT-SCRAPE rule - SKIP",
                'notcnt' => $donecnt,
            ];

        } else {

            // get WhoIs from input (link)
            //$input->logText("Get WhoIs from input (link): ".$input->url );
            $whois = scartWhois::getHostingInfo($input->url);
            if ($whois['status_success']) {

                $url = parse_url($input->url);

                // log old/new for history
                $newip = (isset($whois['domain_ip']) ? $whois['domain_ip'] : '');
                $newtxt = ($input->url_ip) ? "Detected IP change in analyze input" : "Set IP in analyze input";
                $input->logHistory(SCART_INPUT_HISTORY_IP,$input->url_ip,$newip,$newtxt);
                $input->url_ip = $newip;

                $input->url_host = (isset($url['host']) ? $url['host'] : '');

                $input->registrar_abusecontact_id = $whois[SCART_REGISTRAR.'_abusecontact_id'];

                $input->logHistory(SCART_INPUT_HISTORY_HOSTER,
                    $input->host_abusecontact_id,$whois[SCART_HOSTER.'_abusecontact_id'],"Analyze; found hoster in WhoIs");

                $input->host_abusecontact_id = $whois[SCART_HOSTER.'_abusecontact_id'];

                // 2021/2/15/Gs: add proxy_abusecontact_id if set
                $input = Abusecontact::fillProxyservice($input,$whois);

                $input->logText("Set Whois information");
                $input->save();

                $input->logText("Input (link) ".$input->url."; " . $whois['status_text'] .
                    "; registrar_owner=".$whois['registrar_owner'].
                    ", host_owner=".$whois['host_owner'].
                    ", proxy_abusecontact_id=".$input->proxy_abusecontact_id.
                    ", country=".$whois['host_country']);

            } else {

                // @TO-DO: whois_error_retry count??
                $input->logText("Warning WhoIs; looking up: " . $whois['status_text'] );

            }

            // check if direct classify rule

            if ($settings = scartRules::checkDirectClassify($input)) {

                // direct_classify -> set classify and directly to checkonline
                scartLog::logLine("D-Direct_classify rule active for '$input->url' ");

                $status = true;

                if ($settings['rule_type_code'] == SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL) {

                    // only DIRECT_CLASSIFY when not ignore

                    if ($input->grade_code != SCART_GRADE_IGNORE) {

                        // set status, grading and type (always)
                        $input->grade_code = SCART_GRADE_ILLEGAL;
                        $input->firstseen_at = date('Y-m-d H:i:s');
                        $input->online_counter = 0;   // start first NTD
                        $input->type_code = $settings['type_code_'.SCART_GRADE_QUESTION_GROUP_ILLEGAL][0];
                        if ($settings['police_first'][0] == 'y') {

                            // log old/new for history
                            $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_FIRST_POLICE,"Direct classify rule, first police; illegal");

                            $input->status_code = SCART_STATUS_FIRST_POLICE;

                            // also reason if set
                            if (isset($settings['police_reason'][0])) {
                                $clone = new Grade_answer();
                                $clone->record_id = $input->id;
                                $clone->record_type = SCART_INPUT_TYPE;
                                $clone->grade_question_id = (isset($settings['police_reason'][0]['grade_question_id']) ? $settings['police_reason'][0]['grade_question_id'] : 0);
                                $clone->answer = (isset($settings['police_reason'][0]['answer']) ? serialize($settings['police_reason'][0]['answer']) : '');
                                $clone->save();
                            }

                        } else {

                            // log old/new for history
                            $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_SCHEDULER_CHECKONLINE,"Direct classify rule; illegal");

                            $input->status_code = SCART_STATUS_SCHEDULER_CHECKONLINE;
                        }
                        // reset error counters
                        $input->browse_error_retry = $input->whois_error_retry = 0;
                        $input->save();
                        $input->logText("Direct_classify; illegal classification set based on rule; status set on: '$input->status_code' ");

                        // add mainurl parent table
                        $iteminp = Input_parent::where('parent_id',$input->id)->where('input_id',$input->id)->first();
                        if (!$iteminp) {
                            $iteminp = new Input_parent();
                            $iteminp->parent_id = $input->id;
                            $iteminp->input_id = $input->id;
                            $iteminp->save();
                        }

                        $warning = "Mainurl in DIRECT CLASSIFY ILLEGAL rule - classified - status set on '$input->status_code' ";

                        if (scartICCAMinterface::isActive()) {
                            scartICCAMinterface::exportReport($input);
                        }

                    } else {

                        scartLog::logLine("D-Ignore SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL rule because grade of record is IGNORE (filenumber=$input->filenumber) "  );

                    }

                } elseif ($settings['rule_type_code'] == SCART_RULE_TYPE_DIRECT_CLASSIFY_NOT_ILLEGAL) {

                    // log old/new for history
                    $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_CLOSE,"Direct classify rule; not illegal");

                    // set status, grading and type (always)
                    $input->grade_code = SCART_GRADE_NOT_ILLEGAL;
                    $input->firstseen_at = date('Y-m-d H:i:s');
                    $input->online_counter = 1;
                    $input->type_code = $settings['type_code_'.SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL][0];
                    $input->status_code = SCART_STATUS_CLOSE;
                    $input->save();
                    $input->logText("Direct_classify; NOT illegal classification set based on rule; status set on: '$input->status_code' ");

                    $warning = "Mainurl in DIRECT CLASSIFY NOT ILLEGAL rule - classified - status set on '$input->status_code' ";

                } else {

                    $status = false;
                    scartLog::logLine("E-Unkown settings[rule_type_code]=" . $settings['rule_type_code'] );

                }

                if ($status) {

                    // 2021/2/17/Gs: check if classify not set from ICCAM

                    $iccamclassify = $input->getExtrafieldValue(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION);
                    if ($iccamclassify != 'yes') {

                        // copy classification (!)

                        foreach ($settings['grades'] AS $answer) {
                            $clone = new Grade_answer();
                            $clone->record_id = $input->id;
                            $clone->record_type = SCART_INPUT_TYPE;
                            $clone->grade_question_id = $answer['grade_question_id'];
                            $clone->answer = serialize($answer['answer']);
                            $clone->save();
                        }

                    } else {

                        scartLog::logLine("D-Skip setting classifications for SCART_RULE_TYPE_DIRECT_CLASSIFY rule because ICCAM classify is set (=yes)" );

                    }

                    $result = [
                        'status' => true,
                        'warning' => $warning,
                        'notcnt' => $donecnt,
                    ];

                }

                $stat_upd += 1;

            } else {

                $images = scartBrowser::getImages($input->url, $input->url_referer);
                $imgcnt = count($images);
                $input->logText("Input (link) $input->url; found $imgcnt image".(($imgcnt!=1)?'s':'') );

                $min_image_size = Systemconfig::get('abuseio.scart::scheduler.scrape.min_image_size', 0);

                foreach ($images AS $image) {

                    $src = $image['src'];
                    $hash = $image['hash'];

                    try {

                        if (filter_var($src, \FILTER_VALIDATE_URL) === false) {

                            $input->logText("Warning; URL not valid - image url=$src - SKIP (!)");
                            $stat_skip += 1;

                        } elseif ($image['isBase64']) {

                            $input->logText("Warning; found BASE64 sourcecode image - image url=$src - SKIP (!)");
                            $stat_skip += 1;

                        } elseif (scartRules::doNotScrape($src)) {

                            $input->logText("Warning; imageurl '$src' in DO-NOT-SCRAPE rule - SKIP" );

                            // delete cache if filled
                            scartBrowser::delImageCache($hash);
                            $stat_skip += 1;

                        } elseif (in_array($image['type'],[SCART_URL_TYPE_IMAGEURL,SCART_URL_TYPE_SCREENSHOT]) && $image['src'] == $input->url && $imgcnt==1) {

                            // MAINURL = IMAGEURL or VIDEOURL (=screenshot)

                            // NOTE: no hash check in database, so can be double

                            $input->logText("Image (url) is mainurl" );

                            $input->url_hash = $hash;
                            $input->url_base = $image['base'];
                            // hold on to mainurl
                            //$input->url_type = $image['type'];
                            $input->url_host = $image['host'];
                            $input->url_image_width = $image['width'];
                            $input->url_image_height = $image['height'];

                            // 2021/1/29/Gs: do not reset
                            // remove classification answers
                            //Grade_answer::where('record_id',$input->id)->delete();
                            // reset classification
                            //$input->grade_code = SCART_GRADE_UNSET;

                            // add mainurl to items table
                            $iteminp = Input_parent::where('parent_id',$input->id)->where('input_id',$input->id)->first();
                            if (!$iteminp) {
                                $iteminp = new Input_parent();
                                $iteminp->parent_id = $input->id;
                                $iteminp->input_id = $input->id;
                                $iteminp->save();
                                $input->logText("Add mainurl for classification ");
                            }

                            $delivered_items += 1;
                            $stat_upd += 1;

                        } elseif ($image['type'] == SCART_URL_TYPE_SCREENSHOT) {

                            // NOTE: not all Browser providers will return SCART_URL_TYPE_SCREENSHOT  (eg BrowserDragon does this)

                            // check if imgcnt=2; when image[2]=src then mainurl=imageurl


                            if ($imgcnt==2 && $images[1]['src'] == $input->url && $images[1]['type'] == SCART_URL_TYPE_IMAGEURL) {

                                // load mainurl with 1 image found - skip screenshot (=image)

                                // NOTE: no hash check in database, so can be double

                                $input->logText("Image (url) is mainurl" );

                                $image = $images[1];

                                $input->url_hash = $image['hash'];
                                $input->url_base = $image['base'];
                                // hold on to mainurl
                                //$input->url_type = $image['type'];
                                $input->url_host = $image['host'];
                                $input->url_image_width = $image['width'];
                                $input->url_image_height = $image['height'];

                                // 2021/1/29/Gs: do not RESET
                                // remove classification answers
                                //Grade_answer::where('record_id',$input->id)->delete();
                                // reset classification
                                //$input->grade_code = SCART_GRADE_UNSET;

                                // add mainurl to items table
                                $iteminp = Input_parent::where('parent_id',$input->id)->where('input_id',$input->id)->first();
                                if (!$iteminp) {
                                    $iteminp = new Input_parent();
                                    $iteminp->parent_id = $input->id;
                                    $iteminp->input_id = $input->id;
                                    $iteminp->save();
                                    $input->logText("Add mainurl for classification ");
                                }

                                $delivered_items += 1;
                                $stat_upd += 1;

                                // exit foreach images
                                break;

                            } else {

                                // add screenshot as first image

                                $input->logText("Found screenshot for mainurl " );

                                $input->url_hash = $hash;
                                $input->url_base = $image['base'];
                                // hold on to mainurl
                                //$input->url_type = $image['type'];
                                $input->url_host = $image['host'];
                                $input->url_image_width = $image['width'];
                                $input->url_image_height = $image['height'];

                                // 2021/1/29/Gs: do not RESET
                                // remove classification answers
                                //Grade_answer::where('record_type',SCART_INPUT_TYPE)->where('record_id',$input->id)->delete();
                                // reset classification
                                //$input->grade_code = SCART_GRADE_UNSET;

                                // add mainurl to items table
                                $iteminp = Input_parent::where('parent_id',$input->id)->where('input_id',$input->id)->first();
                                if (!$iteminp) {
                                    $iteminp = new Input_parent();
                                    $iteminp->parent_id = $input->id;
                                    $iteminp->input_id = $input->id;
                                    $iteminp->save();
                                    $input->logText("Add mainurl for classification ");
                                }

                                // don't count
                                //$delivered_items += 1;
                                $stat_upd += 1;

                            }

                        } elseif (($image['type'] != SCART_URL_TYPE_VIDEOURL) && ($image['imgsize'] <= $min_image_size) ) {

                            //$input->logText("Warning; skip to small image (size=".$image['imgsize']."); min=$min_image_size; url=$src" );
                            scartLog::logLine("D-Size image '$src' to small (".$image['imgsize']." <= $min_image_size); skip image");
                            // delete cache if filled
                            scartBrowser::delImageCache($hash);
                            $stat_skip += 1;

                        } else {

                            // image found

                            $whois = scartWhois::getHostingInfo($src);
                            if ($whois['status_success']) {

                                $input->logText("Item (image) $src; " . $whois['status_text'] .
                                    "; registrar_owner=".$whois['registrar_owner'].
                                    ", host_owner=". $whois['host_owner'] .
                                    ", host_country=" . $whois['host_country'].
                                    ")");

                                // check HASH check database

                                $hashcheck_at = date('Y-m-d H:i:s');
                                // Note: if HASH check off, then empty
                                $hashcheck_format = scartHASHcheck::getFormat();
                                // Note: if HASH check off, then always false
                                $hashcheck_return = scartHASHcheck::inDatabase($image['data']);

                                if ($item = Input::getItemOnUrl($src) ) {

                                    // already found

                                    /**
                                     * What if connected to other input and already classified?
                                     *   -> and already in the check online status?
                                     *
                                     * Reset status to classification, but reuse classification
                                     *   -> if hash unchanged
                                     *
                                     */

                                    if ($item->url_hash != $hash) {

                                        // CHANGED IMAGE HASH (!)

                                        // reset image
                                        $item->url_hash = $hash;
                                        $item->url_base = $image['base'];
                                        $item->url_type = $image['type'];
                                        $item->url_host = $image['host'];
                                        $item->url_image_width = $image['width'];
                                        $item->url_image_height = $image['height'];

                                        // 2021/1/29/Gs: do not delete
                                        // remove classification answers
                                        //Grade_answer::where('record_type',SCART_INPUT_TYPE)->where('record_id',$item->id)->delete();

                                        // reset classification
                                        $item->grade_code = SCART_GRADE_UNSET;

                                        $item->logText('Found other image (hash) - classification reset ');
                                        scartLog::logLine("D-Connect existing item (filenumber=$item->filenumber) - RESET classification ");

                                    } else {

                                        $item->logText('Found again (same hash) - classification unchanged ');
                                        scartLog::logLine("D-Connect existing item (filenumber=$item->filenumber) ");

                                    }

                                    // new classify -> remove from NTD(s) if found
                                    if ($item->grade_code == SCART_GRADE_ILLEGAL) {

                                        // always be sure to remove
                                        Ntd::removeUrlgrouping($item->url);
                                        $item->logText("Removed from any (grouping) NTD's");

                                    }

                                    // whois info can be changed
                                    $item->registrar_abusecontact_id = $whois[SCART_REGISTRAR.'_abusecontact_id'];

                                    $item->logHistory(SCART_INPUT_HISTORY_HOSTER,
                                        $item->host_abusecontact_id,$whois[SCART_HOSTER.'_abusecontact_id'],"Analyze; found hoster in WhoIs");

                                    $item->host_abusecontact_id = $whois[SCART_HOSTER.'_abusecontact_id'];

                                    // log old/new for history
                                    $item->logHistory(SCART_INPUT_HISTORY_STATUS,$item->status_code,SCART_STATUS_GRADE,"Back to classify (found on url in new scrape)");

                                    // url already in database -> back to grade again
                                    $item->status_code = $item->classify_status_code = SCART_STATUS_GRADE;

                                    $item->hashcheck_at = $hashcheck_at;
                                    $item->hashcheck_format = $hashcheck_format;
                                    $item->hashcheck_return = $hashcheck_return;
                                    // save -> get ID
                                    $item->save();

                                    // 2021/2/15/Gs: add proxy_abusecontact_id if set
                                    $item = Abusecontact::fillProxyservice($item,$whois);
                                    $item->save();

                                    // ** hashcheck **

                                    if ($hashcheck_return) {

                                        $input->logText("Warning; imageurl '$src' in HASH database - direct classify");

                                        // ILLEGAL

                                        $item->grade_code = SCART_GRADE_ILLEGAL;
                                        $settings = scartHASHcheck::getClassification();
                                        // set classification based on setting array
                                        if ($settings['police_first'][0] == 'y') {
                                            $item->classify_status_code = SCART_STATUS_FIRST_POLICE;
                                        }
                                        $item->type_code = $settings['type_code_illegal'][0];
                                        // remove old
                                        Grade_answer::where('record_type',SCART_INPUT_TYPE)->where('record_id',$item->id)->delete();
                                        // set classify
                                        foreach ($settings['grades'] AS $answer) {
                                            $clone = new Grade_answer();
                                            $clone->record_id = $item->id;
                                            $clone->record_type = SCART_INPUT_TYPE;
                                            $clone->grade_question_id = $answer['grade_question_id'];
                                            $clone->answer = serialize($answer['answer']);
                                            $clone->save();
                                        }
                                        // save updates
                                        $item->save();
                                    }

                                    // make connection (if not there)

                                    $iteminp = Input_parent::where('parent_id',$input->id)->where('input_id',$item->id)->first();
                                    if (!$iteminp) {
                                        $iteminp = new Input_parent();
                                        $iteminp->parent_id = $input->id;
                                        $iteminp->input_id = $item->id;
                                        $iteminp->save();
                                        $item->logText("Connected to mainurl (filenumber=$input->filenumber) " );
                                    } else {
                                        $item->logText("Already connected to mainurl (filenumber=$input->filenumber) " );
                                    }

                                    $stat_upd += 1;

                                } elseif ($onhash = Input::getItemOnHash($hash) ) {

                                    // same HASH -> grading already in database

                                    // other url, so create new record

                                    $item = new Input();
                                    $item->url = $src;

                                    // log old/new for history
                                    $newip = (isset($whois['domain_ip']) ? $whois['domain_ip'] : '');
                                    $item->url_ip = $newip;

                                    $item->url_base = $image['base'];
                                    $item->url_referer = $input->url_referer;
                                    $item->url_type = $image['type'];
                                    $item->url_host = $image['host'];
                                    $item->url_hash = $hash;
                                    $item->url_image_width = $image['width'];
                                    $item->url_image_height = $image['height'];
                                    $item->reference = '';  // do not copy from input
                                    $item->workuser_id = $input->workuser_id;

                                    // whois info
                                    $item->registrar_abusecontact_id = $whois[SCART_REGISTRAR.'_abusecontact_id'];
                                    $item->host_abusecontact_id = $whois[SCART_HOSTER.'_abusecontact_id'];

                                    $item->status_code = $item->classify_status_code = SCART_STATUS_GRADE;
                                    // copy basic fields
                                    $item->source_code = $input->source_code;
                                    $item->grade_code = $onhash->grade_code;
                                    $item->type_code = $input->type_code;
                                    $item->received_at = $input->received_at;
                                    $item->hashcheck_at = $hashcheck_at;
                                    $item->hashcheck_format = $hashcheck_format;
                                    $item->hashcheck_return = $hashcheck_return;
                                    $item->save();

                                    $item->logHistory(SCART_INPUT_HISTORY_IP,'',$newip,"Set IP in analyze input");
                                    $item->logHistory(SCART_INPUT_HISTORY_HOSTER,'',$item->host_abusecontact_id,"Analyze; found hoster in WhoIs");
                                    $item->logHistory(SCART_INPUT_HISTORY_STATUS,'',SCART_STATUS_GRADE,"Analyze: new item based on url, same hash");

                                    $iteminp = new Input_parent();
                                    $iteminp->parent_id = $input->id;
                                    $iteminp->input_id = $item->id;
                                    $iteminp->save();

                                    $item->logText("Connect to mainurl (filenumber=$input->filenumber) " );

                                    // 2021/2/15/Gs: add proxy_abusecontact_id if set
                                    $item = Abusecontact::fillProxyservice($item,$whois);
                                    $item->save();

                                    // ** hashcheck **

                                    if ($hashcheck_return) {

                                        $input->logText("Found url '$src' in HASH database - direct classify");

                                        // ILLEGAL

                                        $item->grade_code = SCART_GRADE_ILLEGAL;
                                        $settings = scartHASHcheck::getClassification();
                                        // set classification based on setting array
                                        if ($settings['police_first'][0] == 'y') {
                                            $item->classify_status_code = SCART_STATUS_FIRST_POLICE;
                                        }
                                        $item->type_code = $settings['type_code_illegal'][0];
                                        // set classify
                                        foreach ($settings['grades'] AS $answer) {
                                            $clone = new Grade_answer();
                                            $clone->record_id = $item->id;
                                            $clone->record_type = SCART_INPUT_TYPE;
                                            $clone->grade_question_id = $answer['grade_question_id'];
                                            $clone->answer = serialize($answer['answer']);
                                            $clone->save();
                                        }
                                        // save updates
                                        $item->save();

                                    } else {

                                        // copy grading answers
                                        $answers = Grade_answer::where('record_type',SCART_INPUT_TYPE)->where('record_id',$onhash->id)->get();
                                        foreach ($answers AS $answer) {
                                            $clone = new Grade_answer();
                                            $clone->record_id = $item->id;
                                            $clone->record_type = $answer->record_type;
                                            $clone->grade_question_id = $answer->grade_question_id;
                                            $clone->answer = $answer->answer;
                                            $clone->save();
                                        }

                                        $item->logText("New item with classification based on existing (hash) item" );

                                    }

                                    $stat_new += 1;

                                } else {

                                    //$input->logText("Creating new item" );

                                    $item = new Input();
                                    $item->url = $src;

                                    // log old/new for history
                                    $newip = (isset($whois['domain_ip']) ? $whois['domain_ip'] : '');
                                    $item->url_ip = $newip;

                                    $item->url_base = $image['base'];
                                    $item->url_referer = $input->url_referer;
                                    $item->url_type = $image['type'];
                                    $item->url_host = $image['host'];
                                    $item->url_hash = $hash;
                                    $item->url_image_width = $image['width'];
                                    $item->url_image_height = $image['height'];
                                    $item->reference = '';  // do not copy from input
                                    $item->workuser_id = $input->workuser_id;

                                    // whois info
                                    $item->registrar_abusecontact_id = $whois[SCART_REGISTRAR.'_abusecontact_id'];
                                    $item->host_abusecontact_id = $whois[SCART_HOSTER.'_abusecontact_id'];

                                    $item->status_code = $item->classify_status_code = SCART_STATUS_GRADE;
                                    // copy basic fields
                                    $item->source_code = $input->source_code;
                                    $item->type_code = $input->type_code;
                                    $item->received_at = $input->received_at;
                                    $item->hashcheck_at = $hashcheck_at;
                                    $item->hashcheck_format = $hashcheck_format;
                                    $item->hashcheck_return = $hashcheck_return;
                                    $item->save();

                                    $item->logHistory(SCART_INPUT_HISTORY_IP,'',$newip,"Set IP in analyze input");
                                    $item->logHistory(SCART_INPUT_HISTORY_HOSTER,'',$item->host_abusecontact_id,"Analyze; found hoster in WhoIs");
                                    $item->logHistory(SCART_INPUT_HISTORY_STATUS,'',SCART_STATUS_GRADE,"Analyze: new url found");

                                    // 2021/2/15/Gs: add proxy_abusecontact_id if set
                                    $item = Abusecontact::fillProxyservice($item,$whois);
                                    $item->save();

                                    // ** hashcheck **

                                    if ($hashcheck_return) {

                                        $item->logText("Found url '$src' in HASH database - direct classify");

                                        // ILLEGAL

                                        $item->grade_code = SCART_GRADE_ILLEGAL;
                                        $settings = scartHASHcheck::getClassification();
                                        // set classification based on setting array
                                        if ($settings['police_first'][0] == 'y') {
                                            $item->classify_status_code = SCART_STATUS_FIRST_POLICE;
                                        }
                                        $item->type_code = $settings['type_code_illegal'][0];
                                        // set classify
                                        foreach ($settings['grades'] AS $answer) {
                                            $clone = new Grade_answer();
                                            $clone->record_id = $item->id;
                                            $clone->record_type = SCART_INPUT_TYPE;
                                            $clone->grade_question_id = $answer['grade_question_id'];
                                            $clone->answer = serialize($answer['answer']);
                                            $clone->save();
                                        }
                                        // save updates
                                        $item->save();

                                    }

                                    // connect
                                    $iteminp = new Input_parent();
                                    $iteminp->parent_id = $input->id;
                                    $iteminp->input_id = $item->id;
                                    $iteminp->save();
                                    $item->logText("Connect to mainurl (filenumber=$input->filenumber) " );

                                    $item->logText("New (url) item" );

                                    $stat_new += 1;
                                }

                                $delivered_items += 1;

                            } else {

                                $input->logText("No WhoIs; skip; item (image) $src; " . $whois['status_text']);

                            }

                        }


                    } catch (\Exception $err) {

                        scartLog::logLine("E-doAnalyze exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

                        $input->logText("Warning; can not process image url=$src - message: " . $err->getMessage());
                        $stat_skip += 1;

                    }

                }

            }

            $input->logText("$stat_new record(s) inserted, $stat_upd record(s) updated and $stat_skip images skipped" );
            $donecnt = $stat_new + $stat_upd;

            $input->delivered_items = $delivered_items;
            $input->save();

        }

        if ($result=='') {
            if ($donecnt == 0 ) {
                $result = [
                    'status' => false,
                    'warning' => 'No image(s) found',
                    'notcnt' => 0,
                ];
                $input->logText("No image(s) found (?)" );
            } else {
                $result = [
                    'status' => true,
                    'warning' => '',
                    'notcnt' => $donecnt,
                ];
            }
        }

        // return true when ok
        return $result;
    }


}
