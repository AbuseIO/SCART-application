<?php

/**
 * Wrapper for  open source package phpWhois.org
 * https://github.com/sparc/phpWhois.org
 *
 * AbuseIO specific; extract fields in general fields
 *
 * 2019/11/2/Gs:
 * - added errormail when host_owner UNKNOWN -> futher (technical whois) investigation
 *
 */

namespace reportertool\eokm\classes;

use League\Flysystem\Exception;
use System\Helpers\DateTime;
use Config;
use Log;
use reportertool\eokm\classes\ertLog;

//use phpWhois\Whois;

include_once 'phpwhoissrc/whois.main.php';

/*
// PHP 7.3 version
include_once 'punycode/Punycode.php';
include_once 'niko9911phpWhois/DomainHandlerMap.php';
include_once 'niko9911phpWhois/ImmutableResponse.php';
include_once 'niko9911phpWhois/Query.php';
include_once 'niko9911phpWhois/QueryUtils.php';
include_once 'niko9911phpWhois/Response.php';
include_once 'niko9911phpWhois/Whois.php';
include_once 'niko9911phpWhois/Handler/HandlerBase.php';
include_once 'niko9911phpWhois/Provider/ProviderAbstract.php';
include_once 'niko9911phpWhois/Provider/WhoisServer.php';
*/

class ertWhoisphpWhois {

    // max 1 query each second -> if true then sleep (1)
    static $_freeversion = true;
    static $_freesleep = 1.5;

    static $_dm_registrar_abuse_contact_str = 'Registrar Abuse Contact Email:';
    static $_dm_registrar_country_str = 'Registrant Country:';
    static $_ripe_org_name_str = 'org-name:';
    static $_ripe_role_str = 'role:';
    static $_ripe_comment_abuse_contact_str = '% Abuse contact';
    static $_ripe_country_str = '% Abuse contact';

    /**
     * lookup host; extract WhoIs from host and from IP; return registrar and hosting owner/abuse info
     *
     * @param $host
     * @return array
     */

    public static function lookup($host,$returnresults=false) {

        // init output vars
        $registrar_owner = $registrar_abusecontact = $registrar_country = '';
        $host_owner = $host_abusecontact = $host_country = $ipurl = '';
        $domain_rawtext = $ip_rawtext = '';

        $status_success = false;

        $whois = new \Whois();

        // extract main domain
        $arrhost = explode('.', $host);
        $maindomain = (count($arrhost) > 1) ? $arrhost[count($arrhost)-2] . '.' . $arrhost[count($arrhost)-1] : '';

        // input is host from image
        if ($maindomain) {

            //ertLog::logLine("D-whois->Lookup($maindomain)");
            $dresult = $whois->Lookup($maindomain);
            if (self::$_freeversion) {
                ertLog::logLine("D-WhoIs free version; sleep (".self::$_freesleep.")");
                sleep(self::$_freesleep);
            }
            //trace_log($dresult);

            if ($dresult) {

                // 2019-12-3; first domain sponsor
                if (isset($dresult['regrinfo']['domain']['sponsor'][0])) {
                    $registrar_owner = $dresult['regrinfo']['domain']['sponsor'][0];
                } elseif (isset($dresult['regyinfo']['registrar'])) {
                    $registrar_owner = $dresult['regyinfo']['registrar'];
                }
                // sponsor country
                if (isset($dresult['regrinfo']['domain']['sponsor'][3])) {
                    $registrar_country = $dresult['regrinfo']['domain']['sponsor'][3];
                }

                if (isset($dresult['regrinfo']['domain']['sponsor'][6])) {
                    $registrar_abusecontact = $dresult['regrinfo']['domain']['sponsor'][6];
                }

                foreach ($dresult['rawdata'] AS $key => $value) {
                    $value = trim($value);
                    $pos = stripos($value,self::$_dm_registrar_abuse_contact_str);
                    //ertLog::logLine("D-rawdata; pos=$pos; [$key]=$value");
                    if ($pos !== false) {
                        $registrar_abusecontact = trim(substr($value, strlen(self::$_dm_registrar_abuse_contact_str) + 1));
                    }
                    $pos = stripos($value,self::$_dm_registrar_country_str);
                    if ($pos !== false) {
                        $registrar_country = trim(substr($value, strlen(self::$_dm_registrar_country_str) + 1));
                    }
                }

                // sometimes list of registrar names
                $registrar_owner = (is_array($registrar_owner)) ? implode(' ',$registrar_owner) : $registrar_owner;

                $domain_rawtext .= "\nDOMAIN LOOKUP\n\n";
                $domain_rawtext .= implode("\n", $dresult['rawdata']) . "\n";

                $ipurl = gethostbyname($host);
                //$nresult = self::query($ipurl);

                //ertLog::logLine("D-WhoIs->lookup; maindomain=$maindomain, ip=$ipurl ");
                $nresult = $whois->Lookup($ipurl);
                if (self::$_freeversion) sleep(2);
                //trace_log($nresult);

                if ($nresult) {

                    // directly set?
                    if (isset($nresult['regrinfo']['owner']['organization'])) {
                        $host_owner = $nresult['regrinfo']['owner']['organization'];
                    } elseif (isset($nresult['regrinfo']['admin']['address'][0])) {
                        $host_owner = $nresult['regrinfo']['admin']['address'][0];
                    }

                    if (isset($nresult['regrinfo']['network']['country'])) {
                        $host_country = $nresult['regrinfo']['network']['country'];
                    } elseif (isset($nresult['regrinfo']['owner']['address']['country'])) {
                        $host_country = $nresult['regrinfo']['owner']['address']['country'];
                    } elseif (isset($nresult['regrinfo']['owner'][0]['address']['country'])) {
                        $host_country = $nresult['regrinfo']['owner'][0]['address']['country'];
                    }
                    if (isset($nresult['regrinfo']['abuse']['email'])) {
                        $host_abusecontact = $nresult['regrinfo']['abuse']['email'];
                    }

                    // if we didn't get it in fields, we have to check the raw info
                    if (!$host_country || !$host_owner || !$host_abusecontact) {
                        // walk through array and extract fields
                        foreach ($nresult['rawdata'] AS $key => $value) {
                            $value = trim($value);
                            if (!$host_owner) {
                                $pos = stripos($value, self::$_ripe_org_name_str);
                                if ($pos !== false) {
                                    $host_owner = trim(substr($value, strlen(self::$_ripe_org_name_str) + 1));
                                }
                            }
                            if (!$host_country) {
                                $pos = stripos($value, self::$_ripe_country_str);
                                if ($pos !== false) {
                                    $host_country = trim(substr($value, strlen(self::$_ripe_country_str) + 1));
                                }
                            }
                            if (!$host_abusecontact) {
                                $pos = stripos($value, self::$_ripe_comment_abuse_contact_str);
                                if ($pos !== false) {
                                    // grep emailadres somewhere in this line
                                    preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $value, $matches);
                                    if (count($matches) > 0) {
                                        if (count($matches[0]) > 0) {
                                            // abuse email in comment string
                                            $host_abusecontact = $matches[0][0];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // sometimes list of owners
                    $host_owner = (is_array($host_owner)) ? implode(' ',$host_owner) : $host_owner;

                    if ($host_owner) {
                        // sometimes array result
                        $host_owner = (is_array($host_owner)) ? implode(' ',$host_owner) : $host_owner;
                    }

                    $ip_rawtext .= "\nNETWORK LOOKUP\n\n";
                    $ip_rawtext .= implode("\n", $nresult['rawdata']) ."\n";

                    $status_success = true;
                    $status_text = "WhoIs lookup (maindomain=$maindomain, ip=$ipurl) succeeded";

                } else {
                    $status_text = 'Error WhoIs lookup from IP: ' .$ipurl;
                    ertLog::logLine("E-" . $status_text);
                }

            } else {
                $status_text = 'Error WhoIs lookup; input parameter host not set ';
                ertLog::logLine("E-" . $status_text);
            }

        } else {
            $status_text = "Error url_base with NO maindomain!? (host=$host) ";
            ertLog::logLine('E-'.$status_text);
        }

        $result = [
            'domain_ip' => $ipurl,
            'registrar_lookup' => $maindomain,
            'registrar_owner' => $registrar_owner,
            'registrar_abusecontact' => $registrar_abusecontact,
            'registrar_country' => $registrar_country,
            'registrar_rawtext' => $domain_rawtext,
            'host_lookup' => $ipurl,
            'host_owner' => $host_owner,
            'host_abusecontact' => $host_abusecontact,
            'host_country' => $host_country,
            'host_rawtext' => $ip_rawtext,
            'status_success' => $status_success,
            'status_text' => $status_text,
        ];
        if ($returnresults) {
            $result['domainresult'] = $dresult;
            $result['ipresult'] = $nresult;
        }
        //trace_log($result);

        return $result;
    }

}
