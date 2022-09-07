<?php
namespace abuseio\scart\classes\rules;

use Illuminate\Foundation\Console\PackageDiscoverCommand;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Domainrule;
use abuseio\scart\models\Whois;
use Db;

class scartRules {

    static $_cached = [];

    public static function resetCache() {

        SELF::$_cached = [];
    }

    static function getRule($domain,$rule_type,$extension_also=false) {

        $domainspec = [];
        // strip www. always
        $domainspec[] = str_replace('www.','',$domain);
        if ($extension_also) {
            // also on extension (with .)
            $elms = explode('.', $domain);
            $ext = (count($elms) > 0) ? '.'.$elms[count($elms) - 1] : '';
            if ($ext) $domainspec[] = $ext;
        }

        // search rule -> more specific first
        if (is_array($rule_type)) {
            $rule = Domainrule::whereIn('type_code',$rule_type)
                ->where('enabled',true)
                ->whereIn('domain',$domainspec)
                ->orderBy(Db::raw('LENGTH(domain)'),'desc')
                ->first();
        } else {
            $rule = Domainrule::where('type_code', $rule_type)
                ->where('enabled',true)
                ->whereIn('domain', $domainspec)
                ->orderBy(Db::raw('LENGTH(domain)'),'desc')
                ->first();
        }

        if ($rule) {
            scartLog::logLine("D-scartRules.getRule: found rule domain '$rule->domain' (type=$rule->type_code) for domain '$domain' ");
        }
        return ($rule) ? $rule : '';

    }


    static function getRuleOnParts($domain,$rule_type) {

        $rule = '';

        /**
         * Find rule for domain elements:
         * domain=a.b.c.d
         * elements:
         * - a.b.c.d
         * - b.c.d
         * - c.d
         * - .d
         *
         */

        // Find domain
        //scartLog::logLine("D-domain=$domain, rule_type=" . print_r($rule_type, true) );
        $elms = explode('.', $domain);
        $domaintst = [];
        while (count($elms) > 0) {
            $tst = implode('.',$elms);
            if (count($elms)==1) $tst = '.'.$tst;
            $domaintst[] = $tst;
            array_shift($elms);
        }
        //scartLog::logLine("D-domaintst=". print_r($domaintst,true) );
        if (count($domaintst) > 0) {
            // Note: find
            if (is_array($rule_type)) {
                $rule = Domainrule::whereIn('type_code',$rule_type)
                    ->where('enabled',true)
                    ->whereIn('domain',$domaintst)
                    ->orderBy(Db::raw('LENGTH(domain)'),'desc')
                    ->first();
            } else {
                $rule = Domainrule::where('type_code',$rule_type)
                    ->where('enabled',true)
                    ->whereIn('domain',$domaintst)
                    ->orderBy(Db::raw('LENGTH(domain)'),'desc')
                    ->first();
            }
            if ($rule) {
                scartLog::logLine("D-scartRules.getRule: found rule domain '$rule->domain' (type=$rule->type_code) for domain '$domain' ");
            }
        }

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
                    $rule = self::getRule($host,SCART_RULE_TYPE_NONOTSCRAPE);
                    if ($rule) {
                        $doNot = true;
                    }
                }
                SELF::$_cached[$cachekey] = $doNot;
            }
        }
        if ($doNot) scartLog::logLine("D-scartRules.doNotScrape($inputurl) found $addtxt" );
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
     * Cache always rules within session
     *
     * @param $inputurl
     * @return array
     */

    public static function getRulesWhois($inputurl) {

        $rulewhois = [
            SCART_RULE_TYPE_WHOIS_FILLED => false,
            SCART_RULE_TYPE_PROXY_SERVICE => false,
            SCART_RULE_TYPE_HOST_WHOIS => false,
            SCART_RULE_TYPE_REGISTRAR_WHOIS => false,
        ];

        $url = parse_url($inputurl);
        if ($url !== false) {
            $host = (isset($url['host']) ? $url['host'] : '');
            if ($host) {

                $cachekey = 'getRulesWhois#'.$host;

                if (isset(SELF::$_cached[$cachekey])) {

                    $rulewhois = SELF::$_cached[$cachekey];
                    scartLog::logLine("D-scartRules.getRulesWhois: host=$host, RULE WHOIS CACHED ");

                } else {

                    // prio 1 = proxy_service else host_whois
                    $rule = self::getRule($host,SCART_RULE_TYPE_PROXY_SERVICE);
                    if ($rule) {

                        // find -> get abusecontact and whois (rawtext)

                        $ipurl = $rule->ip;
                        $abusecontact = Abusecontact::find($rule->abusecontact_id);

                        if ($abusecontact) {

                            $info = "Whois HOSTER overruled by proxy_service rule for '$host'";
                            scartLog::logLine("D-scartRules.getRulesWhois: $info");
                            $whois = Whois::findAC($rule->abusecontact_id,SCART_HOSTER);
                            $rulewhois = array_merge($rulewhois,[
                                'domain_ip' => $ipurl,
                                SCART_HOSTER.'_lookup' => $ipurl,
                                SCART_HOSTER.'_owner' => $abusecontact->owner,
                                SCART_HOSTER.'_abusecontact' => $abusecontact->abusecustom,
                                SCART_HOSTER.'_country' => $abusecontact->abusecountry,
                                SCART_HOSTER.'_rawtext' => ($whois) ? $whois->rawtext : $info,
                                SCART_HOSTER.'_abusecontact_id' => $rule->abusecontact_id,
                            ]);
                            $rulewhois[SCART_RULE_TYPE_PROXY_SERVICE] = true;

                        } else {
                            scartLog::logLine("W-Rule host_whois set, but abusecontact (id=$rule->abusecontact_id) not found!?");
                        }

                    } else {

                        // check if host_whois rule

                        $rule = self::getRule($host,SCART_RULE_TYPE_HOST_WHOIS);
                        if ($rule) {

                            $ipurl = gethostbyname($host);
                            $abusecontact = Abusecontact::find($rule->abusecontact_id);

                            if ($abusecontact) {

                                $info = "Whois HOSTER overruled by host_whois rule for '$host'; set on '$abusecontact->owner' ";
                                scartLog::logLine("D-scartRules.getRulesWhois: $info");

                                $whois = Whois::findAC($rule->abusecontact_id,SCART_HOSTER);
                                $rulewhois = array_merge($rulewhois, [
                                    'domain_ip' => $ipurl,
                                    SCART_HOSTER.'_lookup' => $ipurl,
                                    SCART_HOSTER.'_owner' => $abusecontact->owner,
                                    SCART_HOSTER.'_abusecontact' => $abusecontact->abusecustom,
                                    SCART_HOSTER.'_country' => $abusecontact->abusecountry,
                                    SCART_HOSTER.'_rawtext' => ($whois) ? $whois->rawtext : $info,
                                    SCART_HOSTER.'_abusecontact_id' => $rule->abusecontact_id,
                                ]);
                                $rulewhois[SCART_RULE_TYPE_HOST_WHOIS] = true;

                            } else {
                                scartLog::logLine("W-Rule host_whois set, but abusecontact (id=$rule->abusecontact_id) not found!?");
                            }

                        }

                    }

                    // check if registrar_whois rule

                    $rule = self::getRule($host,SCART_RULE_TYPE_REGISTRAR_WHOIS);
                    if ($rule) {

                        $abusecontact = Abusecontact::find($rule->abusecontact_id);

                        if ($abusecontact) {

                            $info = "Whois REGISTRAR overruled by registrar_whois rule for '$host'; set on '$abusecontact->owner' ";
                            scartLog::logLine("D-scartRules.getRulesWhois: $info");

                            $whois = Whois::findAC($rule->abusecontact_id,SCART_REGISTRAR);
                            $rulewhois = array_merge($rulewhois, [
                                SCART_REGISTRAR.'_lookup' => $host,
                                SCART_REGISTRAR.'_owner' => $abusecontact->owner,
                                SCART_REGISTRAR.'_abusecontact' => $abusecontact->abusecustom,
                                SCART_REGISTRAR.'_country' => $abusecontact->abusecountry,
                                SCART_REGISTRAR.'_rawtext' => ($whois) ? $whois->rawtext : $info,
                                SCART_REGISTRAR.'_abusecontact_id' => $rule->abusecontact_id,
                            ]);
                            $rulewhois[SCART_RULE_TYPE_REGISTRAR_WHOIS] = true;

                        } else {
                            scartLog::logLine("W-Rule registrar_whois set, but abusecontact (id=$rule->abusecontact_id) not found!?");
                        }

                    }

                    if (isset($rulewhois['registrar_abusecontact_id']) && isset($rulewhois['host_abusecontact_id']) ) {
                        // set success if WHOIS is totally filled by rules
                        $rulewhois[SCART_RULE_TYPE_WHOIS_FILLED] = true;
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
                    scartLog::logLine("D-scartRules.checkSiteOwnerRule: host=$host, RULE SITEOWNER CACHED ");

                } else {

                    $rule = self::getRule($host,SCART_RULE_TYPE_SITE_OWNER);
                    if ($rule) {
                        $siteownerac_id = $rule->abusecontact_id;
                        SELF::$_cached[$cachekey] =  $siteownerac_id;
                    }

                }

            }
        }

        return $siteownerac_id;
    }


    public static function checkDirectClassify($input) {

        $settings = false;

        $url = parse_url($input->url);
        if ($url !== false) {
            $host = (isset($url['host']) ? $url['host'] : '');
            if ($host) {

                // add source_code + hotlineid from input as cachekey (because of extra validation, see below)
                $hotlineid = $input->getExtrafieldValue( SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_HOTLINEID);
                $cachekey = 'checkDirectClassify#'.$host.$input->source_code.$hotlineid;

                if (isset(SELF::$_cached[$cachekey])) {

                    $settings = SELF::$_cached[$cachekey];
                    scartLog::logLine("D-scartRules.checkDirectClassify: host=$host, RULE CLASSIFY CACHED ");

                } else {

                    $rule = self::getRule($host,[SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL,SCART_RULE_TYPE_DIRECT_CLASSIFY_NOT_ILLEGAL],true);
                    if ($rule) {
                        // get rulesetdata and extract settings
                        $settings = ($rule->rulesetdata) ? unserialize($rule->rulesetdata) : false;
                        if ($settings) {

                            $valid = true;
                            $settings['rule_type_code'] = $rule->type_code;

                            // check if only for specific source
                            if (!empty($settings['source_code_illegal'])) {

                                // declared or '' (all)
                                $valid = (in_array('',$settings['source_code_illegal']) || in_array($input->source_code,$settings['source_code_illegal']));
                                // valid is false when not found
                                if ($valid) {
                                    // check if only for specific (iccam) hotline
                                    if ($input->source_code == SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM && !empty($settings['iccam_hotline_illegal'])) {
                                        if ($hotlineid) {
                                            // declared or '' (all)
                                            $valid = (in_array('',$settings['iccam_hotline_illegal']) || in_array($hotlineid,$settings['iccam_hotline_illegal']));
                                        }
                                        if (!$valid) {
                                            scartLog::logLine("D-scartRules.checkDirectClassify [id=$rule->id]: hotlineID '$hotlineid' NOT in: " . implode(',',$settings['iccam_hotline_illegal']));
                                        }
                                    }
                                } else {
                                    scartLog::logLine("D-scartRules.checkDirectClassify [id=$rule->id]; source '$input->source_code' NOT in: " . implode(',',$settings['source_code_illegal']) );
                                }
                            }

                            if ($valid) {
                                scartLog::logLine("D-scartRules.checkDirectClassify [id=$rule->id]: valid rule (id=$rule->id) for filenumer '$input->filenumber' ");
                                SELF::$_cached[$cachekey] =  $settings;
                            } else {
                                $settings = false;
                            }

                        }
                    }

                }

            }
        }

        return $settings;
    }

    public static function linkCheckerOnline($inputurl) {

        $addon = '';

        $url = parse_url($inputurl);
        if ($url !== false) {
            $host = (isset($url['host']) ? $url['host'] : '');
            if ($host) {

                $addon_id = 0;

                $cachekey = 'linkCheckerOnline#'.$host;
                if (isset(SELF::$_cached[$cachekey])) {

                    $addon_id = SELF::$_cached[$cachekey];
                    scartLog::logLine("D-scartRules.linkCheckerOnline: host=$host, addon_id=$addon_id; RULE CLASSIFY CACHED ");

                } else {

                    $rule = self::getRule($host,[SCART_RULE_TYPE_LINK_CHECKER],true);
                    if ($rule) {
                        SELF::$_cached[$cachekey] = $addon_id = $rule->addon_id;
                        scartLog::logLine("D-scartRules.linkCheckerOnline: host=$host found addon_id=$addon_id ");
                    }

                }

                if (!empty($addon_id)) {
                    $addon = Addon::find($addon_id);
                    if (!$addon) {
                        scartLog::logLine("E-scartRules.linkCheckerOnline: CANNOT FIND ADDON!? (addon_id=$addon_id) ");
                        unset(SELF::$_cached[$cachekey]);
                    }
                }

            }
        }

        return $addon;
    }

    public static function hasProxyServiceAPI() {

        $cnt  = Domainrule::where('type_code', SCART_RULE_TYPE_PROXY_SERVICE_API)
            ->where('enabled',true)
            ->count();
        return ($cnt > 0);
    }

    public static function getProxyServiceRules() {

        return Domainrule::where('type_code', SCART_RULE_TYPE_PROXY_SERVICE)
            ->where('enabled',true)
            ->where('proxy_abusecontact_id','<>',0)
            ->get();
    }

    public static function proxyServiceAPI($abusecontact_id) {

        // TESTING; https://cloudigirl.com/

        $addon_id = '';

        $cachekey = 'proxyServiceAPI#'.$abusecontact_id;
        if (isset(SELF::$_cached[$cachekey])) {

            $addon_id = SELF::$_cached[$cachekey];
            scartLog::logLine("D-scartRules.proxyServiceAPI: abusecontact_id=$abusecontact_id, RULE CACHED ");

        } else {

            $rule = Domainrule::where('type_code', SCART_RULE_TYPE_PROXY_SERVICE_API)
                ->where('enabled',true)
                ->where('abusecontact_id', $abusecontact_id)
                ->first();
            if ($rule) {
                SELF::$_cached[$cachekey] = $addon_id = $rule->addon_id;
                scartLog::logLine("D-scartRules.proxyServiceAPI: abusecontact_id=$abusecontact_id, found addon_id=$addon_id ");
            }

        }

        return $addon_id;
    }

}
