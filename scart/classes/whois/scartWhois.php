<?php

namespace abuseio\scart\classes\whois;

/**
 * getHostingInfo; main function
 * - load (whois) provider for actual IP/domain extern lookup
 * - cache within this session same IP / domain lookups
 * - domainrules (scartRules) can overrule lookup (goes first)
 *
 * verifyWhoIs
 * - use getHostingInfo
 * - check if changed -> set status so calling function can ack
 *
 * providers:
 * - WhoisHexilion
 * - WhoisphpWhois
 *
 * 2020/7/27/database cache
 * - use database cache with max-age;
 *
 */

use League\Flysystem\Exception;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Whois;
use System\Helpers\DateTime;
use Config;
use Log;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;

class scartWhois  {

    static $_cachedlookup = [];
    static $_provider = '';

    function __construct() {

        // load default
        SELF::$_provider = Systemconfig::get('abuseio.scart::whois.provider', '');
    }

    public static function setProvider($provider) {
        SELF::$_provider = $provider;
        scartLog::logLine("D-scartWhois.setProvider($provider) ");
    }

    public static function resetCache() {

        SELF::$_cachedlookup = [];
    }


    public static function lookupIP($ip,$returnresults=false) {

        if (SELF::$_provider=='') SELF::$_provider = Systemconfig::get('abuseio.scart::whois.provider', '');

        if (SELF::$_provider) {

            if ($ip) {

                $result = [
                    'status_success' => true,
                    'status_text' => 'Load from WHOIS cache',
                ];

                if ($host_abusecontact_id = scartWhoisCache::getWhoisCache($ip,SCART_WHOIS_TARGET_IP) ) {

                    // save abusecontact_id
                    $result[SCART_HOSTER . '_abusecontact_id'] = $host_abusecontact_id;
                    // connect (maintain) SCART_HOSTER whois info
                    $result = Whois::fillWhoisArray($result, $host_abusecontact_id, SCART_HOSTER);
                    // overrule WHOIS lookup ip -> use always current
                    $result[SCART_HOSTER.'_lookup'] = $ip;

                } else {

                    // set provider class
                    $classname = 'abuseio\scart\classes\whois\scartWhois'.SELF::$_provider;

                    // CACHE NOT FILLED OF TO OLD -> LOOKUP HOSTER
                    scartLog::logLine("D-scartWhois.lookupIP; dynamic lookup IP WhoIs from $ip (whois provider=".SELF::$_provider.") " . (($returnresults) ? '(returnresults=true)' : '') );
                    $result = call_user_func($classname.'::lookupIp',$ip,$returnresults);

                    if ($result['status_success']) {
                        scartLog::logLine("D-scartWhois.lookupIP; host.owner=".$result['host_owner'].", host.abusecontact=".$result['host_abusecontact']);
                    }

                }

            } else {

                $result = [
                    'status_success' => false,
                    'status_text' => "error; no IP specified "
                ];

            }

        } else {

            $result = [
                'status_success' => false,
                'status_text' => "error; whos provider "
            ];

        }

        return $result;
    }

    public static function lookupDomain($host,$returnresults=false) {

        if (SELF::$_provider=='') SELF::$_provider = Systemconfig::get('abuseio.scart::whois.provider', '');

        if (SELF::$_provider) {

            if ($host) {

                try {

                    $result = [
                        'status_success' => true,
                        'status_text' => 'Load from WHOIS cache',
                    ];

                    $maindomain = self::getMaindomain($host);   // always main domain, exclude subdomains
                    if ($registrar_abusecontact_id = scartWhoisCache::getWhoisCache($maindomain,SCART_WHOIS_TARGET_DOMAIN)) {
                        // save abusecontact_id
                        $result[SCART_REGISTRAR.'_abusecontact_id'] = $registrar_abusecontact_id;
                        // connect (maintain) SCART_REGISTRAR whois info
                        $result = Whois::fillWhoisArray($result,$registrar_abusecontact_id,SCART_REGISTRAR);
                        // overrule WHOIS lookup domain -> use always current
                        $result[SCART_REGISTRAR.'_lookup'] = $maindomain;

                    } else {

                        // set provider class
                        $classname = 'abuseio\scart\classes\whois\scartWhois'.SELF::$_provider.'::lookupDomain';

                        // CACHE NOT FILLED OR TO OLD -> LOOKUP REGISTRAR
                        scartLog::logLine("D-scartWhois.lookupDomain; dynamic lookup DOMAIN WhoIs host=$host (whois provider=".SELF::$_provider.") " . (($returnresults) ? '(returnresults=true)' : '') );

                        $result = call_user_func($classname,$host,$returnresults);
                        //$result = ['status_success' => false,'status_text' => "not supported",];

                        if ($result['status_success']) {
                            scartLog::logLine("D-scartWhois.lookupDomain; register.owner=".$result['registrar_owner'].", register.abusecontact=".$result['registrar_abusecontact']);
                        }

                    }

                } catch (\Exception $err) {
                    scartLog::logLine("E-scartWhois.lookupDomain; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                    $result = [
                        'status_success' => false,
                        'status_text' => "error lookupDomain: " . $err->getMessage(),
                    ];
                }

            } else {

                $result = [
                    'status_success' => false,
                    'status_text' => 'Host empty!?',
                ];

            }

        } else {

            $result = [
                'status_success' => false,
                'status_text' => "error; whos provider "
            ];

        }

        return $result;
    }

    /**
     * lookupLink
     *
     * 2020/7/27/Gs: integrate WHOIS CACHE
     *
     * First lookup cache within session -> if set, return cached
     * Secondly check WHOIS CACHE database -> if valid, return chaced
     * if database cache to old or not found, then try dynamic lookup
     *
     * @param $link
     * @param bool $returnresults
     * @return array|mixed|string
     */
    public static function lookupLink($link,$returnresults=false) {

        $result = '';

        if (SELF::$_provider=='') SELF::$_provider = Systemconfig::get('abuseio.scart::whois.provider', '');

        if (SELF::$_provider) {

            try {

                scartLog::logLine("D-scartWhois.lookupLink; link=$link");
                $host = self::getHost($link);

                if ($host) {

                    if (isset(self::$_cachedlookup[$host])) {
                        scartLog::logLine("D-scartWhois.lookupLink; load WhoIs $link (host=$host) from CACHE");
                        $result = self::$_cachedlookup[$host];
                        if ($result['status_success']) {
                            $result['status_text'] = "WhoIs lookup (host=$host) loading from CACHE";
                        }
                    } else {

                        /**
                         * First lookup of domain -> when false result, then quit with false status
                         * Second lookup of IP -> when false result, then quit with false status (ALSO when domain lookup success)
                         *
                         */

                        $result = self::lookupDomain($host,$returnresults);

                        if ($result['status_success']) {

                            // note: include subdomains with host; can be different IP's
                            $host_lookup = $result['domain_ip'] = self::getIP($host);

                            if ($host_lookup) {

                                $result = array_merge($result,self::lookupIP($host_lookup,$returnresults));

                            } else {

                                $result = array_merge($result,[
                                    'status_success' => false,
                                    'status_text' => "error; cannot get a valid IP from host '$host'"
                                ]);

                            }

                        }

                        if ($result['status_success']) {

                            if ($returnresults) {
                                $result['domainresult'] = (isset($result['dresult'])) ? $result['dresult'] : 'Unknown';
                                $result['ipresult'] = (isset($result['nresult'])) ? $result['nresult'] : 'Unknown';
                            }

                            self::$_cachedlookup[$host] = $result;

                        } else {

                            scartLog::logLine("W-scartWhois.lookupLink: error: " . $result['status_text'] );

                        }

                    }

                } else {
                    scartLog::logLine("W-scartWhois.lookupLink: error; cannot extract host from link '$link'");
                    $result = [
                        'status_success' => false,
                        'status_text' => "error; cannot extract host from link '$link'"
                    ];
                }
            } catch (\Exception $err) {
                scartLog::logLine("E-scartWhois.lookupLink; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                $result = [
                    'status_success' => false,
                    'status_text' => "error lookuplink: " . $err->getMessage(),
                ];
            }
        } else {
            scartLog::logLine("E-scartWhois.lookupLink: error NO WHOIS PROVIDER SET in environment!?!");
            $result = [
                'status_success' => false,
                'status_text' => "error NO WHOIS PROVIDER SET in environment!?!"
            ];
        }

        if ($result==='') {
            scartLog::logLine("E-scartWhois.lookupLink: unknown error!?!");
            $result = [
                'status_success' => false,
                'status_text' => "unknown error in lookup whois!?!"
            ];
        }

        return $result;
    }

    /**
     * MAIN function to get hostinginfo -> WHOIS provider
     * Maintain Abusecontact and WhoIs information
     *
     * There are two main functions for getting the WHOIS informatie;
     * a) lookup whois -> get info from RIPE and tld
     * a) rules -> set hoster/proxy/registrar directly
     *
     * Both functions has a session cache.
     * Whois lookup has also a database cache.
     * Rules overrule lookup whois
     *
     * An array is filled with hoster, registrar and proxy information.
     * Also with the status getting the hosting information
     * And some data for housekeeping.
     *
     * @param $link
     * @return array|mixed|string
     */
    public static function getHostingInfo($link,$usecache=true) {

        /**
         * 2021/3/15/gs
         *
         * First get whois based on url (host)
         * Can be dynamic lookup, also can be from whois cache
         *
         * Secondly check the rules
         * These overrule (always) the whois information
         *
         */

        // disable cache when not used
        if (!$usecache) scartWhoisCache::setDisabled(true);

        // WHOIS query with returnresult needed for unknown
        $whois = self::lookupLink($link,true);

        if ($whois['status_success']) {

            // if still okay

            if ($whois['status_success']) {

                // REGISTRAR -> can be set by rules -> when not, then fill

                if (!isset($whois[SCART_REGISTRAR.'_abusecontact_id'])) {

                    // find or create abusecontact registrar_owner
                    $abusecontact = Abusecontact::findCreateOwner($whois['registrar_owner'],$whois['registrar_abusecontact'],$whois['registrar_country'],SCART_REGISTRAR);
                    if ($abusecontact) {

                        // save abusecontact_id
                        $whois[SCART_REGISTRAR.'_abusecontact_id'] = $abusecontact->id;
                        // connect (maintain) SCART_REGISTRAR whois info
                        $whois = Whois::connectAC($abusecontact,SCART_REGISTRAR,$whois);
                        // if cache to old, cache
                        if (!scartWhoisCache::validWhoisCache($whois[SCART_REGISTRAR.'_lookup'],SCART_WHOIS_TARGET_DOMAIN)) {
                            // 2020/7/27/Gs: added caching
                            scartWhoisCache::setWhoisCache($whois[SCART_REGISTRAR.'_lookup'],SCART_WHOIS_TARGET_DOMAIN,$abusecontact->id);
                        }


                    } else {

                        // empty abusecontact_id
                        $whois[SCART_REGISTRAR.'_abusecontact_id'] = 0;

                        scartLog::logLine("W-getHostingInfo; empty (0) abusecontact; ignore ");

                        // send operator alert
                        // 2019/10/21/Gs: to much mails (spam...)
                        /*
                        scartAlerts::insertAlert(SCART_ALERT_LEVEL_WARNING,'abuseio.scart::mail.whois_empty_abusecontact',[
                            'url' => $link,
                        ]);
                        */

                    }

                }

                // HOSTER -> can be set by rules -> when not, then fill

                if (!isset($whois[SCART_HOSTER.'_abusecontact_id'])) {

                    // find or create abusecontact host_owner
                    $abusecontact = Abusecontact::findCreateOwner($whois['host_owner'],$whois['host_abusecontact'],$whois['host_country'],SCART_HOSTER);
                    if ($abusecontact) {

                        // save abusecontact_id
                        $whois[SCART_HOSTER.'_abusecontact_id'] = $abusecontact->id;
                        // connect (maintain) SCART_HOSTER whois info
                        $whois = Whois::connectAC($abusecontact,SCART_HOSTER,$whois);
                        // if cache to old, set cache
                        if (!scartWhoisCache::validWhoisCache($whois[SCART_HOSTER.'_lookup'],SCART_WHOIS_TARGET_IP)) {
                            // 2020/7/27/Gs: added caching
                            scartWhoisCache::setWhoisCache($whois[SCART_HOSTER.'_lookup'],SCART_WHOIS_TARGET_IP,$abusecontact->id);
                        }

                    } else {

                        // empty abusecontact_id
                        $whois[SCART_HOSTER.'_abusecontact_id'] = 0;
                        // 2019/10/21/Gs: to much mails (spam...)
                        scartLog::logLine("W-getHostingInfo; empty (0) abusecontact; ignore ");
                        // send operator alert
                        /*
                        scartAlerts::insertAlert(SCART_ALERT_LEVEL_WARNING,'abuseio.scart::mail.whois_empty_abusecontact',[
                            'url' => $link,
                        ]);
                        */

                    }

                }

            }

        }

        // Check rules
        $rulesWhois = scartRules::getRulesWhois($link);

        // check if PROXY set
        if ($rulesWhois[SCART_RULE_TYPE_PROXY_SERVICE]) {

            if (!isset($whois[SCART_HOSTER . '_abusecontact_id'])) {
                // find/create abusecontact
                if (isset($whois['host_owner'])) {
                    $abusecontact = Abusecontact::findCreateOwner($whois['host_owner'],$whois['host_abusecontact'],$whois['host_country'],SCART_HOSTER);
                } else {
                    $abusecontact = '';
                }
                $host_abusecontact_id = ($abusecontact) ? $abusecontact->id : 0;
                // if not found then skip
            } else {
                $host_abusecontact_id = $whois[SCART_HOSTER . '_abusecontact_id'];
            }

            // save the (proxy) hoster in seperate field
            $whois[SCART_HOSTER.'_proxy_abusecontact_id'] = $host_abusecontact_id;

        }

        $whois = array_merge($whois,$rulesWhois);

        // Note: if lookupLink is not status_succes (error lookup) and rulesWhois overrules, then status still false

        // Check/fill always unknown flags
        $owner = isset($whois[SCART_HOSTER.'_owner']) ? $whois[SCART_HOSTER.'_owner'] : '';
        $whois[SCART_HOSTER.'_unknown'] =  ($owner == '');
        if ($whois[SCART_HOSTER.'_unknown']) {
            // report/dump raw when owner not found
            // reset essential fields
            $whois[SCART_HOSTER.'_owner'] = SCART_WHOIS_UNKNOWN;
            $whois[SCART_HOSTER.'_abusecontact'] = '';
            $whois[SCART_HOSTER.'_country'] = '';
            // FALSE RESULT (!)
            self::logUnknown(SCART_HOSTER,$link,(isset($whois['ipresult'])?$whois['ipresult']:'Unknown'));
            $whois['status_success'] = false;
            $whois['status_text'] = SCART_HOSTER.' owner is EMPTY!?';
        }
        $owner = isset($whois[SCART_REGISTRAR.'_owner']) ? $whois[SCART_REGISTRAR.'_owner'] : '';
        $whois[SCART_REGISTRAR.'_unknown'] =  ($owner == '');
        if ($whois[SCART_REGISTRAR.'_unknown']) {
            // report/dump raw when owner not found
            // reset essential fields
            $whois[SCART_REGISTRAR.'_owner'] = SCART_WHOIS_UNKNOWN;
            $whois[SCART_REGISTRAR.'_abusecontact'] = '';
            $whois[SCART_REGISTRAR.'_country'] = '';
            self::logUnknown(SCART_REGISTRAR,$link,(isset($whois['domainresult'])?$whois['domainresult']:'Unknown') );
            // can be empty... unknown
        }

        // reset disable cache
        if (!$usecache) scartWhoisCache::setDisabled(false);

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

        $errortext = "scartWhoisphpWhois; host=$host, maindomain=$maindomain; cannot find $type OWNER ";
        scartLog::logLine("W-$errortext");

        //$errortext .= CRLF_NEWLINE . $logresult;
        //scartLog::errorMail($errortext,null,"scartWhoisphpWhois; cannot find $type OWNER");
    }


    /**
     * verify host/registrar
     *
     * if changed, then change and send alert
     *
     * @param $record  (by reference!)
     *
     */
    public static function verifyWhoIs($record, $sendalert=true, $usecache=true) {

        // verify WHOIS

        $changed = false;

        $url = $record->url;

        // get Hostinginfo based on RULES and WHOIS query
        $whois = self::getHostingInfo($url,$usecache);

        if ($whois['status_success']) {

            $whois[SCART_REGISTRAR.'_changed'] = $whois[SCART_HOSTER.'_changed'] = false;
            $whois[SCART_REGISTRAR.'_changed_logtext'] = $whois[SCART_HOSTER.'_changed_logtext'] = '';

            if (!$whois[SCART_REGISTRAR.'_unknown'] && ($record->registrar_abusecontact_id != $whois[SCART_REGISTRAR.'_abusecontact_id']) ) {

                $maindomain = (isset($whois['registrar_lookup'])?$whois['registrar_lookup']:'');
                scartLog::logLine("D-Whois; REGISTRAR info changed; maindomain=$maindomain");

                $oldcontact = Abusecontact::find($record->registrar_abusecontact_id);
                $oldowner = ($oldcontact) ? $oldcontact->owner . " ($oldcontact->filenumber)" : SCART_ABUSECONTACT_OWNER_EMPTY;

                $record->registrar_abusecontact_id = $whois[SCART_REGISTRAR.'_abusecontact_id'];
                $newcontact = Abusecontact::find($record->registrar_abusecontact_id);
                $newowner = ($newcontact) ? $newcontact->owner . " ($newcontact->filenumber)" : SCART_ABUSECONTACT_OWNER_EMPTY;

                // 2022/6/27 check if registrar active -> if not, then no alert
                $registrar_active = Systemconfig::get('abuseio.scart::ntd.registrar_active',true);

                if ($registrar_active && $newcontact && !$whois[SCART_RULE_TYPE_REGISTRAR_WHOIS] && $sendalert) {

                    $newabuse = [
                        'url' => $record->url,
                        'url_filenumber' => $record->filenumber,
                        'whois_type' => strtoupper(SCART_REGISTRAR),
                        'oldowner' => $oldowner,
                        'newowner' => $newowner,
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,
                        'abuseio.scart::mail.whois_changed', $newabuse);

                } else {
                    scartLog::logLine("D-Sendalert OFF; whois[SCART_RULE_TYPE_REGISTRAR_WHOIS]=".$whois[SCART_RULE_TYPE_REGISTRAR_WHOIS] );
                }

                $whois[SCART_REGISTRAR.'_changed'] = true;
                $whois[SCART_REGISTRAR.'_changed_logtext'] = "changed registrar from '$oldowner' to '$newowner'";

                $changed = true;
            }

            // Note: if $whois[SCART_HOSTER.'_unknown'] then NOT here
            if ($record->host_abusecontact_id != $whois[SCART_HOSTER.'_abusecontact_id'] ) {

                scartLog::logLine("D-Whois; HOST info changed");

                $oldcontact = Abusecontact::find($record->host_abusecontact_id);
                $oldowner = ($oldcontact) ? $oldcontact->owner . " ($oldcontact->filenumber)" : SCART_ABUSECONTACT_OWNER_EMPTY;

                $record->logHistory(SCART_INPUT_HISTORY_HOSTER,
                    $record->host_abusecontact_id,$whois[SCART_HOSTER.'_abusecontact_id'],"VerifyWhoIs; hoster in WhoIs changed");

                // set new record hoster
                $record->host_abusecontact_id = $whois[SCART_HOSTER.'_abusecontact_id'];
                $newcontact = Abusecontact::find($record->host_abusecontact_id);
                $newowner = ($newcontact) ? $newcontact->owner . " ($newcontact->filenumber)" : SCART_ABUSECONTACT_OWNER_EMPTY;

                // 2021/2/15/Gs: check proxy fields
                $record = Abusecontact::fillProxyservice($record,$whois);

                if ($newcontact && !$whois[SCART_RULE_TYPE_HOST_WHOIS] && !$whois[SCART_RULE_TYPE_PROXY_SERVICE] && $sendalert) {

                    $newabuse = [
                        'url' => $record->url,
                        'url_filenumber' => $record->filenumber,
                        'whois_type' => strtoupper(SCART_HOSTER),
                        'oldowner' => $oldowner,
                        'newowner' => $newowner,
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,
                        'abuseio.scart::mail.whois_changed', $newabuse);

                } else {
                    scartLog::logLine(
                        "D-Sendalert OFF; whois[SCART_RULE_TYPE_HOST_WHOIS]=".$whois[SCART_RULE_TYPE_HOST_WHOIS] .
                        ", whois[SCART_RULE_TYPE_PROXY_SERVICE]=".$whois[SCART_RULE_TYPE_PROXY_SERVICE] );
                }

                $whois[SCART_HOSTER.'_changed'] = true;
                $whois[SCART_HOSTER.'_changed_logtext'] = "changed hoster from '$oldowner' to '$newowner'";

                $changed = true;
            }

            // always check if IP changed
            // is possible by moving domain to other hoster
            // and also if SCART_RULE_TYPE_PROXY_SERVICE

            if (isset($whois['domain_ip'])) {
                if ($whois['domain_ip'] != $record->url_ip) {
                    scartLog::logLine("D-Whois; IP changed from $record->url_ip to ".$whois['domain_ip']." ");
                    $record->logHistory(SCART_INPUT_HISTORY_IP,$record->url_ip,$whois['domain_ip'],"Detected IP change in get WhoIs");
                    $record->url_ip = $whois['domain_ip'];
                    $changed = true;
                }
            }

            if (!$changed) {
                scartLog::logLine("D-Whois; status_text=" . $whois['status_text'] . "; HOST/REGISTRAR/IP info NOT changed");
            }

        } else {
            // error lookup -> skip
        }

        return $whois;
    }



    //** INTERNALS **/

    public static function getHost($link) {
        $url = parse_url($link);
        $host = trim(isset($url['host']) ? $url['host'] : '');
        return $host;
    }

    static function gethostbyname6($host) {

        // get (first) AAAA record for $host
        $ip6 = false;
        try {
            $dns6 = dns_get_record($host, DNS_AAAA);
            //scartLog::logLine("D-gethostbyname6($host); dns-query=" . print_r($dns6,true));
            foreach ($dns6 AS $record) {
                if ($record["type"] == "AAAA") {
                    $ip6 = $record["ipv6"];
                    break;
                }
            }
        } catch (\Exception $ex) {
            scartLog::logLine("W-gethostbyname6($host); line=".$ex->getLine() . ', message=' . $ex->getMessage());
        }
        return  $ip6;
    }

    public static function getIP($host) {

        $ip = gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP, [FILTER_FLAG_IPV4, FILTER_FLAG_IPV6])) {

            scartLog::logLine("W-Could not get valid IP address for RIPE lookup; host=$host " );

            /*
            // skip IPV6 lookup -> not working yet

            $host_lookup = self::gethostbyname6($host);
            if ($host_lookup) {
                $result = scartRIPEdb::getIPinfo($host_lookup);
                foreach ($result AS $var => $val) {
                    $$var = $val;
                }
            } else {
                scartLog::logLine("W-Could not get valid IP4 or IPV6 address for RIPE lookup; host=$host " );
            }
            */

            $ip = '';
        }
        return $ip;
    }

    public static function getMaindomain($host) {

        $arrhost = explode('.', $host);
        $maindomain = (count($arrhost) > 1) ? $arrhost[count($arrhost)-2] . '.' . $arrhost[count($arrhost)-1] : '';
        return $maindomain;
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


}
