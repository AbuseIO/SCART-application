<?php
namespace reportertool\eokm\classes;

use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Domainrule;
use ReporterTool\EOKM\Models\Whois;

class ertRules {

    static $_cached = [];

    static function getRule($domain,$rule_type) {
        // strip www.
        $domain = str_replace('www.','',$domain);
        $rule = Domainrule::where('type_code',$rule_type)
            ->where('domain',$domain)
            ->first();
        return ($rule) ? $rule : '';
    }

    public static function doNotScrape($inputurl) {

        $doNot = false; $addtxt = '';
        $url = parse_url($inputurl);
        if ($url!==false) {
            $host = (isset($url['host']) ? $url['host'] : '');
            if ($host) {
                $cachekey = 'doNotScrape#'.$host;
                if (isset(SELF::$_cached[$cachekey])) {
                    $doNot = SELF::$_cached[$cachekey];
                    $addtxt = "(CACHED)";
                } else {
                    $rule = self::getRule($host,ERT_RULE_TYPE_NONOTSCRAPE);
                    if ($rule) {
                        $doNot = true;
                    }
                }
                SELF::$_cached[$cachekey] = $doNot;
            }
        }
        if ($doNot) ertLog::logLine("D-ertRules.doNotScrape($inputurl) found $addtxt" );
        return $doNot;
    }

    /**
     *
     * Rules:
     * a. proxy_service -> set host IP + abusecontact
     * b. host_whois -> set host abusecontact
     * c. registrar_whois -> set registrar abusecontact
     *
     * Prio:
     * 1: proxy_service
     * 2: host_whois
     * 3: registrar_whois
     *
     * Return
     * - whois array
     * - success = true if both HOST and REGISTRAR are filled
     *
     * @param $inputurl
     * @return array
     */

    public static function getRulesWhois($inputurl) {

        $rulewhois = [
            ERT_RULE_TYPE_WHOIS_FILLED => false,
            ERT_RULE_TYPE_PROXY_SERVICE => false,
            ERT_RULE_TYPE_HOST_WHOIS => false,
            ERT_RULE_TYPE_REGISTRAR_WHOIS => false,
        ];

        $url = parse_url($inputurl);
        if ($url !== false) {
            $host = (isset($url['host']) ? $url['host'] : '');
            if ($host) {

                $cachekey = 'getRulesWhois#'.$host;

                if (isset(SELF::$_cached[$cachekey])) {

                    $rulewhois = SELF::$_cached[$cachekey];
                    ertLog::logLine("D-ertRules.getRulesWhois: host=$host, WHOIS CACHED ");

                } else {

                    // prio 1 = proxy_service else host_whois
                    $rule = self::getRule($host,ERT_RULE_TYPE_PROXY_SERVICE);
                    if ($rule) {

                        // find -> get abusecontact and whois (rawtext)

                        $ipurl = $rule->ip;
                        $abusecontact = Abusecontact::find($rule->abusecontact_id);

                        if ($abusecontact) {

                            $info = "Whois HOSTER overruled by proxy_service rule for '$host'";
                            ertLog::logLine("D-ertRules.getRulesWhois: $info");
                            $whois = Whois::findAC($rule->abusecontact_id,ERT_HOSTER);
                            $rulewhois = array_merge($rulewhois,[
                                'domain_ip' => $ipurl,
                                ERT_HOSTER.'_lookup' => $ipurl,
                                ERT_HOSTER.'_owner' => $abusecontact->owner,
                                ERT_HOSTER.'_abusecontact' => $abusecontact->abusecustom,
                                ERT_HOSTER.'_country' => $abusecontact->abusecountry,
                                ERT_HOSTER.'_rawtext' => ($whois) ? $whois->rawtext : $info,
                                ERT_HOSTER.'_abusecontact_id' => $rule->abusecontact_id,
                            ]);
                            $rulewhois[ERT_RULE_TYPE_PROXY_SERVICE] = true;

                        } else {
                            ertLog::logLine("W-Rule host_whois set, but abusecontact (id=$rule->abusecontact_id) not found!?");
                        }

                    } else {

                        // check if host_whois rule

                        $rule = self::getRule($host,ERT_RULE_TYPE_HOST_WHOIS);
                        if ($rule) {

                            $ipurl = gethostbyname($host);
                            $abusecontact = Abusecontact::find($rule->abusecontact_id);

                            if ($abusecontact) {

                                $info = "Whois HOSTER overruled by host_whois rule for '$host'; set on '$abusecontact->owner' ";
                                ertLog::logLine("D-ertRules.getRulesWhois: $info");

                                $whois = Whois::findAC($rule->abusecontact_id,ERT_HOSTER);
                                $rulewhois = array_merge($rulewhois, [
                                    'domain_ip' => $ipurl,
                                    ERT_HOSTER.'_lookup' => $ipurl,
                                    ERT_HOSTER.'_owner' => $abusecontact->owner,
                                    ERT_HOSTER.'_abusecontact' => $abusecontact->abusecustom,
                                    ERT_HOSTER.'_country' => $abusecontact->abusecountry,
                                    ERT_HOSTER.'_rawtext' => ($whois) ? $whois->rawtext : $info,
                                    ERT_HOSTER.'_abusecontact_id' => $rule->abusecontact_id,
                                ]);
                                $rulewhois[ERT_RULE_TYPE_HOST_WHOIS] = true;

                            } else {
                                ertLog::logLine("W-Rule host_whois set, but abusecontact (id=$rule->abusecontact_id) not found!?");
                            }

                        }

                    }

                    // check if registrar_whois rule

                    $rule = self::getRule($host,ERT_RULE_TYPE_REGISTRAR_WHOIS);
                    if ($rule) {

                        $abusecontact = Abusecontact::find($rule->abusecontact_id);

                        if ($abusecontact) {

                            $info = "Whois REGISTRAR overruled by registrar_whois rule for '$host'; set on '$abusecontact->owner' ";
                            ertLog::logLine("D-ertRules.getRulesWhois: $info");

                            $whois = Whois::findAC($rule->abusecontact_id,ERT_REGISTRAR);
                            $rulewhois = array_merge($rulewhois, [
                                ERT_REGISTRAR.'_lookup' => $host,
                                ERT_REGISTRAR.'_owner' => $abusecontact->owner,
                                ERT_REGISTRAR.'_abusecontact' => $abusecontact->abusecustom,
                                ERT_REGISTRAR.'_country' => $abusecontact->abusecountry,
                                ERT_REGISTRAR.'_rawtext' => ($whois) ? $whois->rawtext : $info,
                                ERT_REGISTRAR.'_abusecontact_id' => $rule->abusecontact_id,
                            ]);
                            $rulewhois[ERT_RULE_TYPE_REGISTRAR_WHOIS] = true;

                        } else {
                            ertLog::logLine("W-Rule registrar_whois set, but abusecontact (id=$rule->abusecontact_id) not found!?");
                        }

                    }

                    if (isset($rulewhois['registrar_abusecontact_id']) && isset($rulewhois['host_abusecontact_id']) ) {
                        // set success if WHOIS is totally filled by rules
                        $rulewhois[ERT_RULE_TYPE_WHOIS_FILLED] = true;
                    }
                    SELF::$_cached[$cachekey] = $rulewhois;

                }

            }

        }

        return $rulewhois;
    }

    public static function checkSiteOwnerRule($inputurl) {

        $siteownerac_id = 0;

        $url = parse_url($inputurl);
        if ($url !== false) {
            $host = (isset($url['host']) ? $url['host'] : '');
            if ($host) {

                $cachekey = 'checkSiteOwnerRule#'.$host;
                if (isset(SELF::$_cached[$cachekey])) {

                    $siteownerac_id = SELF::$_cached[$cachekey];
                    ertLog::logLine("D-ertRules.checkSiteOwnerRule: host=$host, WHOIS CACHED ");

                } else {

                    $rule = self::getRule($host,ERT_RULE_TYPE_SITE_OWNER);
                    if ($rule) {
                        $siteownerac_id = $rule->abusecontact_id;
                        SELF::$_cached[$cachekey] =  $siteownerac_id;
                    }

                }

            }
        }

        return $siteownerac_id;
    }

}
