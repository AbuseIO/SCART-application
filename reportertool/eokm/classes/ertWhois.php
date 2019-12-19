<?php

namespace reportertool\eokm\classes;

/**
 * LookupLink
 *
 * Returned info:
 *
 * 'domain_ip'                  // found IP by domain
 * 'registrar_owner'            // registrar owner name
 * 'registrar_abusecontact'     // registrar abuse contact
 * 'host_owner'                 // host owner
 * 'host_abusecontact'          // host abuse contact
 * 'rawtext'                    // RAW whois info
 * 'status_success'             // status OKAY
 * 'status_text'                // status message text
 *
 * Note:
 *
 * Do session caching WhoIs info [key=host]
 *
 */

use League\Flysystem\Exception;
use reportertool\eokm\classes\ertRules;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Ntd;
use ReporterTool\EOKM\Models\Whois;
use System\Helpers\DateTime;
use Config;
use Log;

class ertWhois  {

    static $_cachedlookup = [];
    static $_provider = '';

    function __construct() {

        parent::__contruct();

        // load default
        SELF::$_provider = Config::get('reportertool.eokm::whois.provider', '');
    }

    public static function setProvider($provider) {
        SELF::$_provider = $provider;
        ertLog::logLine("D-ertWhois.setProvider($provider) ");
    }

    public static function lookupLink($link,$returnresults=false) {

        $result = '';
        if (SELF::$_provider=='') SELF::$_provider = Config::get('reportertool.eokm::whois.provider', '');

        if (SELF::$_provider) {

            try {
                $url = parse_url($link);
                $host = trim(isset($url['host']) ? $url['host'] : '');
                if ($host) {
                    if (isset(self::$_cachedlookup[$host])) {
                        ertLog::logLine("D-ertWhois.lookupLink; load WhoIs $link (host=$host) from CACHE");
                        $result = self::$_cachedlookup[$host];
                        if ($result['status_success']) {
                            $result['status_text'] = "WhoIs lookup (host=$host) loading from CACHE";
                        }
                    } else {
                        ertLog::logLine("D-ertWhois.lookupLink; dynamic lookup WhoIs from $link (host=$host) (whois provider=".SELF::$_provider.") " . (($returnresults) ? '(returnresults=true)' : '') );
                        $classname = 'reportertool\eokm\classes\ertWhois'.SELF::$_provider;
                        $result = call_user_func($classname.'::lookup',$host,$returnresults);
                        if ($result && $result['status_success']) {
                            self::$_cachedlookup[$host] = $result;
                        }
                    }
                } else {
                    ertLog::logLine("W-ertWhois.lookupLink: error; cannot extract host from link '$link'");
                    $result = [
                        'status_success' => false,
                        'status_text' => "error; cannot extract host from link '$link'"
                    ];
                }
            } catch (\Exception $err) {
                ertLog::logLine("E-ertWhois.lookupLink; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                $result = [
                    'status_success' => false,
                    'status_text' => "error lookuplink: " . $err->getMessage(),
                ];
            }
        } else {
            ertLog::logLine("E-ertWhois.lookupLink: error NO WHOIS PROVIDER SET in environment!?!");
            $result = [
                'status_success' => false,
                'status_text' => "error NO WHOIS PROVIDER SET in environment!?!"
            ];
        }

        return $result;
    }

    public static function htmlOutputRaw($rawtext) {
        return str_replace("\n","<br />\n",$rawtext);
    }

    public static function jsOutputRaw($rawtext) {
        $jsraw = str_replace("\r\n","\n",$rawtext);
        $jsraw = str_replace("\r","\n",$jsraw);
        $jsraw = str_replace("'","\'",$jsraw);
        $jsraw = str_replace("\n",'<br />',$jsraw);
        return $jsraw;
    }

    /**
     * MAIN function to get hostinginfo -> WHOIS provider
     *
     * Maintain Abusecontact and WhoIs information
     *
     * @param $link
     * @return array|mixed|string
     */
    public static function getHostingInfo($link) {

        // any rules?
        $rulesWhois = ertRules::getRulesWhois($link);

        if ($rulesWhois[ERT_RULE_TYPE_WHOIS_FILLED]) {

            // got all information from rules
            $whois = array_merge($rulesWhois, [
                'status_success' => true,
                'status_text' => 'Whois filled by rules',
            ]);

        } else {

            // note: some info can be filled here by a rule (host or registrar)

            // WHOIS query
            $whois = self::lookupLink($link,true);

            if ($whois && $whois['status_success']) {

                // merge with rules subset of whois -> if set (always 'whois_filled', so count > 1)
                if (count($rulesWhois) > 1) {
                    // note: rules overule whois query
                    $whois = array_merge($whois,$rulesWhois);
                }

                // evaluate result -> host_owner and registrar_owner are essential -> detect

                $whois[ERT_HOSTER.'_unknown'] =  ($whois[ERT_HOSTER.'_owner'] == '');
                if ($whois[ERT_HOSTER.'_unknown']) {
                    // report/dump raw when owner not found
                    // reset essential fields
                    $whois[ERT_HOSTER.'_owner'] = ERT_WHOIS_UNKNOWN;
                    $whois[ERT_HOSTER.'_abusecontact'] = '';
                    $whois[ERT_HOSTER.'_country'] = '';
                    // FALSE RESULT (!)
                    self::logUnknown(ERT_HOSTER,$link,$whois['ipresult']);
                    $whois['status_success'] = false;
                    $whois['status_text'] = ERT_HOSTER.' owner is EMPTY!?';
                }
                $whois[ERT_REGISTRAR.'_unknown'] =  ($whois[ERT_REGISTRAR.'_owner'] == '');
                if ($whois[ERT_REGISTRAR.'_unknown']) {
                    // report/dump raw when owner not found
                    // reset essential fields
                    $whois[ERT_REGISTRAR.'_owner'] = ERT_WHOIS_UNKNOWN;
                    $whois[ERT_REGISTRAR.'_abusecontact'] = '';
                    $whois[ERT_REGISTRAR.'_country'] = '';
                    self::logUnknown(ERT_REGISTRAR,$link,$whois['domainresult']);
                    // can be empty... unknown
                }

                // if still okay

                if ($whois['status_success']) {

                    // REGISTRAR -> can be set by rules -> when not, then fill

                    if (!isset($whois[ERT_REGISTRAR.'_abusecontact_id'])) {

                        // find or create abusecontact registrar_owner
                        $abusecontact = Abusecontact::findCreateOwner($whois['registrar_owner'],$whois['registrar_abusecontact'],$whois['registrar_country'],$link);
                        if ($abusecontact) {

                            // save abusecontact_id
                            $whois[ERT_REGISTRAR.'_abusecontact_id'] = $abusecontact->id;
                            // connect (maintain) ERT_REGISTRAR whois info
                            $whois = Whois::connectAC($abusecontact,ERT_REGISTRAR,$whois);

                        } else {

                            // empty abusecontact_id
                            $whois[ERT_REGISTRAR.'_abusecontact_id'] = 0;

                            // 2019/10/21/Gs: to much mails (spam...)
                            ertLog::logLine("W-getHostingInfo; empty (0) abusecontact; ignore ");
                            // send operator alert
                            /*
                            ertAlerts::insertAlert(ERT_ALERT_LEVEL_WARNING,'reportertool.eokm::mail.whois_empty_abusecontact',[
                                'url' => $link,
                            ]);
                            */

                        }

                    }

                    // HOSTER -> can be set by rules -> when not, then fill

                    if (!isset($whois[ERT_HOSTER.'_abusecontact_id'])) {

                        // find or create abusecontact host_owner
                        $abusecontact = Abusecontact::findCreateOwner($whois['host_owner'],$whois['host_abusecontact'],$whois['host_country']);
                        if ($abusecontact) {

                            // save abusecontact_id
                            $whois[ERT_HOSTER.'_abusecontact_id'] = $abusecontact->id;
                            // connect (maintain) ERT_HOSTER whois info
                            $whois = Whois::connectAC($abusecontact,ERT_HOSTER,$whois);

                        } else {

                            // empty abusecontact_id
                            $whois[ERT_HOSTER.'_abusecontact_id'] = 0;
                            // 2019/10/21/Gs: to much mails (spam...)
                            ertLog::logLine("W-getHostingInfo; empty (0) abusecontact; ignore ");
                            // send operator alert
                            /*
                            ertAlerts::insertAlert(ERT_ALERT_LEVEL_WARNING,'reportertool.eokm::mail.whois_empty_abusecontact',[
                                'url' => $link,
                            ]);
                            */

                        }

                    }

                }

            }

        }

        return $whois;
    }

    /**
     * Debugging WhoIs queries - log UNKNOWN WHOIS -> error
     *
     * @param $type
     * @param $host
     * @param $result
     */
    public static function logUnknown($type,$host,$result) {

        //trace_log($result);

        $logresult = print_r($result, true);
        $logresult = str_replace("\n",CRLF_NEWLINE,$logresult);
        $arrhost = explode('.', $host);
        $maindomain = (count($arrhost) > 1) ? $arrhost[count($arrhost)-2] . '.' . $arrhost[count($arrhost)-1] : '?';

        $errortext = "ertWhoisphpWhois; host=$host, maindomain=$maindomain; cannot find $type OWNER ";
        ertLog::logLine("W-$errortext");

        $errortext .= CRLF_NEWLINE . $logresult;
        //ertLog::errorMail($errortext,null,"ertWhoisphpWhois; cannot find $type OWNER");
    }


    /**
     * verify host/registrar
     *
     * if changed, then change and send alert
     *
     * @param $record       BY REFERENCE (!)
     *
     */
    public static function verifyWhoIs($record, $sendalert=true) {

        // verify WHOIS

        $changed = false;

        $url = $record->url;

        // get Hostinginfo based on RULES and WHOIS query
        $whois = self::getHostingInfo($url);

        if ($whois['status_success']) {

            $whois[ERT_REGISTRAR.'_changed'] = $whois[ERT_HOSTER.'_changed'] = false;

            if (!$whois[ERT_REGISTRAR.'_unknown'] && ($record->registrar_abusecontact_id != $whois[ERT_REGISTRAR.'_abusecontact_id']) ) {

                ertLog::logLine("D-Whois; REGISTRAR info changed");

                $oldcontact = Abusecontact::find($record->registrar_abusecontact_id);
                $oldowner = ($oldcontact) ? $oldcontact->owner : ERT_ABUSECONTACT_OWNER_EMPTY;

                // remove from NTD's (if found)
                $ntd = Ntd::removeUrl($record->registrar_abusecontact_id,$url);
                if ($ntd) $ntd->logText("Url (image) '$url' OFFLINE; removed from NTD");

                $record->registrar_abusecontact_id = $whois[ERT_REGISTRAR.'_abusecontact_id'];

                if (!$whois[ERT_RULE_TYPE_REGISTRAR_WHOIS] && $sendalert) {
                    $newcontact = Abusecontact::find($record->registrar_abusecontact_id);
                    if ($newcontact) {

                        ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.whois_changed',[
                            'whois_type' => strtoupper(ERT_REGISTRAR),
                            'url_filenumber' => $record->filenumber,
                            'oldowner' => $oldowner,
                            'abusecontact' => $newcontact->abusecustom,
                            'filenumber' => $newcontact->filenumber,
                        ]);
                    }
                } else {
                    ertLog::logLine("D-No sendalert; sendalert=$sendalert, whois[ERT_RULE_TYPE_REGISTRAR_WHOIS]=".$whois[ERT_RULE_TYPE_REGISTRAR_WHOIS] );
                }

                $whois[ERT_REGISTRAR.'_changed'] = true;

                // done in calling function
                //$record->save();
                $changed = true;
            }

            // Note: if $whois[ERT_HOSTER.'_unknown'] then NOT here
            if ($record->host_abusecontact_id != $whois[ERT_HOSTER.'_abusecontact_id'] ) {
                ertLog::logLine("D-Whois; HOST info changed");

                // remove from NTD's (if found)
                $ntd = Ntd::removeUrl($record->host_abusecontact_id,$url);
                if ($ntd) $ntd->logText("Url (image) '$url' OFFLINE; removed from NTD");

                $record->host_abusecontact_id = $whois[ERT_HOSTER.'_abusecontact_id'];

                if (!$whois[ERT_RULE_TYPE_HOST_WHOIS] && !$whois[ERT_RULE_TYPE_PROXY_SERVICE] && $sendalert) {
                    $abusecontact = Abusecontact::find($record->host_abusecontact_id);
                    if ($abusecontact) {
                        ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.whois_changed',[
                            'whois_type' => strtoupper(ERT_HOSTER),
                            'url_filenumber' => $record->filenumber,
                            'abuseowner' => $abusecontact->owner,
                            'filenumber' => $abusecontact->filenumber,
                            'abusecontact' => $abusecontact->abusecustom,
                        ]);
                    }
                } else {
                    ertLog::logLine(
                        "D-No sendalert; sendalert=$sendalert, whois[ERT_RULE_TYPE_HOST_WHOIS]=".$whois[ERT_RULE_TYPE_HOST_WHOIS] .
                        ", whois[ERT_RULE_TYPE_PROXY_SERVICE]=".$whois[ERT_RULE_TYPE_PROXY_SERVICE] );
                }

                $whois[ERT_HOSTER.'_changed'] = true;

                // done in calling function
                //$record->save();
                $changed = true;
            }

            if (!$changed) {
                ertLog::logLine("D-Whois; status_text=" . $whois['status_text'] . "; HOST/REGISTRAR info NOT changed");
            }

        } else {
            // error lookup -> skip
        }

        return $whois;
    }

}
