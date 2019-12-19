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

namespace reportertool\eokm\classes;

use BackendMenu;
use BackendAuth;
use League\Flysystem\Exception;
use Config;
use Log;
use Mail;

use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertMail;
use reportertool\eokm\classes\ertModel;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Grade_answer;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_input;
use ReporterTool\EOKM\Models\Ntd;
use ReporterTool\EOKM\Models\Ntd_template;
use ReporterTool\EOKM\Models\Ntd_url;
use ReporterTool\EOKM\Models\Whois;

class ertAnalyzeInput {

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
     *   c: make notification
     *
     * - $input->logText(); functional logging
     * - ertLog::logLine(); technical logging
     *
     * @param $input
     * @return result [status,warning,notcnt]
     *
     */

    public static function doAnalyze($input) {

        $input->logText( 'Analyze input: '.$input->url );

        $result = ''; $stat_new = $stat_upd = $stat_skip = $donecnt = $imgcnt = 0;

        if (ertRules::doNotScrape($input->url)) {

            $input->logText("Warning; mainurl '$input->url' in DO NOT SCRAPE rule - SKIP" );

            $result = [
                'status' => false,
                'warning' => "Mainurl '$input->url' in DO-NOT-SCRAPE rule - SKIP",
                'notcnt' => $donecnt,
            ];

        } else {

            $images = ertBrowser::getImages($input->url, $input->url_referer);
            $imgcnt = count($images);
            $input->logText("Input (link) $input->url; found $imgcnt image".(($imgcnt>1)?'s':'') );

            // get WhoIs from input (link)
            //$input->logText("Get WhoIs from input (link): ".$input->url );
            $whois = ertWhois::getHostingInfo($input->url);
            if ($whois['status_success']) {

                $url = parse_url($input->url);
                $input->url_ip = (isset($whois['domain_ip']) ? $whois['domain_ip'] : '');
                $input->url_host = (isset($url['host']) ? $url['host'] : '');

                $input->registrar_abusecontact_id = $whois[ERT_REGISTRAR.'_abusecontact_id'];
                $input->host_abusecontact_id = $whois[ERT_HOSTER.'_abusecontact_id'];

                $input->logText("Set Whois information");
                $input->save();

                $input->logText("Input (link) ".$input->url."; " . $whois['status_text'] .
                    "; registrar_owner=".$whois['registrar_owner'].
                    ", host_owner=".$whois['host_owner'].
                    ", country=".$whois['host_country']);

            } else {

                // Note: whois_error_retry count?

                $input->logText("Warning WhoIs; looking up: " . $whois['status_text'] );


            }

            $min_image_size = Config::get('reportertool.eokm::scheduler.scrape.min_image_size', 0);

            foreach ($images AS $image) {

                $src = $image['src'];
                $hash = $image['hash'];

                if ($image['isBase64']) {

                    $input->logText("Warning; found BASE64 sourcecode image - image url=$src - SKIP (!)");
                    $stat_skip += 1;

                } elseif (($image['type'] != ERT_URL_TYPE_VIDEOURL) && ($image['imgsize'] <= $min_image_size)) {

                    $input->logText("Warning; skip to small image (size=".$image['imgsize']." < $min_image_size) - image url=$src" );

                    // delete cache if filled
                    ertBrowser::delImageCache($hash);
                    $stat_skip += 1;

                } elseif (ertRules::doNotScrape($src)) {

                    $input->logText("Warning; imageurl '$src' in DO-NOT-SCRAPE rule - SKIP" );

                    // delete cache if filled
                    ertBrowser::delImageCache($hash);
                    $stat_skip += 1;

                    // TO-DO: may be this rule after insert in database - delete?

                } else {

                    $whois = ertWhois::getHostingInfo($src);
                    if ($whois['status_success']) {

                        $input->logText("Notification (image) $src; " . $whois['status_text'] .
                            "; registrar_owner=".$whois['registrar_owner'].
                            ", host_owner=". $whois['host_owner'] .
                            ", host_country=" . $whois['host_country'].
                            ")");

                        if ($not = Notification::getOnUrl($src) ) {

                            if ($not->url_hash != $hash) {

                                // CHANGED IMAGE HASH (!)

                                // reset image
                                $not->url_hash = $hash;
                                $not->url_base = $image['base'];
                                $not->url_type = $image['type'];
                                $not->url_host = $image['host'];
                                $not->url_image_width = $image['width'];
                                $not->url_image_height = $image['height'];

                                // remove classification answers
                                Grade_answer::where('record_type',ERT_NOTIFICATION_TYPE)->where('record_id',$not->id)->delete();

                                // remove from NTD if found
                                if ($not->grade_code == ERT_GRADE_ILLEGAL) {

                                    // hoster (if found)
                                    if ($not->host_abusecontact_id!=0) {
                                        $ntd = Ntd::removeUrl($not->host_abusecontact_id, $not->url);
                                        if ($ntd) $ntd->logText("Content '$not->url' OFFLINE or HASH changed; removed from NTD $ntd->filenumber");
                                    }

                                    // registrar (if found)
                                    if ($not->registrar_abusecontact_id!=0) {
                                        $ntd = Ntd::removeUrl($not->registrar_abusecontact_id, $not->url);
                                        if ($ntd) $ntd->logText("Content '$not->url' OFFLINE or HASH changed; removed from NTD $ntd->filenumber");
                                    }

                                }

                                // reset classification
                                $not->grade_code = ERT_GRADE_UNSET;

                                $not->logText('Found other image (hash) - classification reset ');
                                ertLog::logLine("D-Connect existing notification (filenumber=$not->filenumber) - RESET classification ");

                            } else {

                                $not->logText('Found again (same hash) - classification unchanged ');
                                ertLog::logLine("D-Connect existing notification (filenumber=$not->filenumber) ");

                            }

                            // url already in database -> back to grade again
                            $not->status_code = ERT_STATUS_GRADE;
                            $not->save();

                            // make connection (if not there)

                            $notinp = Notification_input::where('input_id',$input->id)->where('notification_id',$not->id)->first();
                            if (!$notinp) {
                                $notinp = new Notification_input();
                                $notinp->input_id = $input->id;
                                $notinp->notification_id = $not->id;
                                $notinp->save();
                                $not->logText("Connected to existing mainurl (filenumber=$input->filenumber) " );
                            } else {
                                $not->logText("Already connected to mainurl (filenumber=$input->filenumber) " );
                            }

                            $stat_upd += 1;

                        } elseif ($onhash = Notification::getOnHash($hash) ) {

                            // same HASH -> grading already in database

                            // other url, so create new record

                            $not = new Notification();
                            $not->url = $src;
                            $not->url_ip = $whois['domain_ip'];
                            $not->url_base = $image['base'];
                            $not->url_referer = $input->url_referer;
                            $not->url_type = $image['type'];
                            $not->url_host = $image['host'];
                            $not->url_hash = $hash;
                            $not->url_image_width = $image['width'];
                            $not->url_image_height = $image['height'];
                            // do not copy from input
                            $not->reference = '';
                            $not->workuser_id = $input->workuser_id;
                            $not->registrar_abusecontact_id = $whois[ERT_REGISTRAR.'_abusecontact_id'];
                            $not->host_abusecontact_id = $whois[ERT_HOSTER.'_abusecontact_id'];
                            $not->status_code = ERT_STATUS_GRADE;
                            // copy grading
                            $not->grade_code = $onhash->grade_code;
                            $not->type_code = $input->type_code;
                            $not->save();

                            $notinp = new Notification_input();
                            $notinp->input_id = $input->id;
                            $notinp->notification_id = $not->id;
                            $notinp->save();

                            // copy grading answers
                            $answers = Grade_answer::where('record_type',ERT_NOTIFICATION_TYPE)->where('record_id',$onhash->id)->get();
                            foreach ($answers AS $answer) {
                                $clone = new Grade_answer();
                                $clone->record_id = $not->id;
                                $clone->record_type = $answer->record_type;
                                $clone->grade_question_id = $answer->grade_question_id;
                                $clone->answer = $answer->answer;
                                $clone->save();
                            }

                            $not->logText("New notification with classification based on existing (hash) notification" );

                            $stat_new += 1;

                        } else {

                            //$input->logText("Creating new notification" );

                            $not = new Notification();
                            $not->url = $src;
                            $not->url_ip = $whois['domain_ip'];
                            $not->url_base = $image['base'];
                            $not->url_referer = $input->url_referer;
                            $not->url_type = $image['type'];
                            $not->url_host = $image['host'];
                            $not->url_hash = $hash;
                            $not->url_image_width = $image['width'];
                            $not->url_image_height = $image['height'];
                            // do not copy from input
                            $not->reference = '';
                            $not->workuser_id = $input->workuser_id;
                            $not->registrar_abusecontact_id = $whois[ERT_REGISTRAR.'_abusecontact_id'];
                            $not->host_abusecontact_id = $whois[ERT_HOSTER.'_abusecontact_id'];
                            $not->status_code = ERT_STATUS_GRADE;
                            $not->type_code = $input->type_code;
                            $not->save();

                            $notinp = new Notification_input();
                            $notinp->input_id = $input->id;
                            $notinp->notification_id = $not->id;
                            $notinp->save();

                            $not->logText("New (url) notification" );

                            $stat_new += 1;
                        }

                    } else {

                        $input->logText("Warning WhoIs; notification (image) $src; " . $whois['status_text']);

                    }

                }

            }

            $input->logText("$stat_new notification(s) inserted, $stat_upd notification(s) updated and $stat_skip images skipped" );

            $donecnt = $stat_new + $stat_upd;
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
