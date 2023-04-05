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
 * 2020/3/26/Gs:
 * - USE RIPE DB QUERY FOR IP (HOSTER) LOOKUP
 *
 * 2021/10/29/Gs:
 * - E_DEPRECATED for phpwhois library -> update/oatch!
 * - but may be registrar functionality in SCART will be dropped
 *
 */

namespace abuseio\scart\classes\whois;

use League\Flysystem\Exception;
use System\Helpers\DateTime;
use Config;
use Log;
use abuseio\scart\classes\helpers\scartLog;

include_once 'phpwhoissrc/whois.main.php';

class scartWhoisphpWhois {

    // max 1 query each second -> if true then sleep (1)
    static $_freeversion = true;
    static $_freesleep = 1.5;
    static $_freesleepCountries = ['.nl'];

    static $_dm_registrar_abuse_contact_str = 'Registrar Abuse Contact Email:';
    static $_dm_registrar_country_str = 'Registrant Country:';

    public static function lookupIp($ipurl,$returnresults=false) {

        try {
            // Direct RIPE lookup
            $result = scartRIPEdb::getIPinfo($ipurl);

        } catch (\Exception $err) {
            scartLog::logLine("E-scartWhoisphpWhois.lookupIp; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            $result = [
                'status_success' => false,
                'status_text' => "error lookuplink: " . $err->getMessage(),
            ];
        }

        return $result;
    }

    public static function lookupDomain($host,$returnresults=false) {

        // init
        $registrar_owner = $registrar_abusecontact = $registrar_country = $domain_rawtext = $dresult = '';

        // registrar information not a "must have"
        $status_success = true;

        try {

            $arrhost = explode('.', $host);
            $maindomain = (count($arrhost) > 1) ? $arrhost[count($arrhost)-2] . '.' . $arrhost[count($arrhost)-1] : '';
            $extdomain = strtolower((count($arrhost) > 1) ? '.' . $arrhost[count($arrhost)-1] : '');

            // input is maindomain from image
            if ($maindomain) {

                // .NL (SIDN) stops free service if more then 1 call within 1 second
                if (self::$_freeversion && in_array($extdomain, self::$_freesleepCountries)) {
                    scartLog::logLine("D-scartWhoisphpWhois.WhoIs free version; sleep (" . self::$_freesleep . "); extension=$extdomain ");
                    sleep(self::$_freesleep);
                } else {
                    scartLog::logLine("D-scartWhoisphpWhois.WhoIs free version; NO sleep; extension=$extdomain ");
                }

                //scartLog::logLine("D-whois->Lookup($maindomain)");
                $whois = new \Whois();
                $dresult = $whois->Lookup($maindomain);

                //scartLog::logLine("D-Lookupdomain: dresult=".print_r($dresult,true));

                if ($dresult) {

                    // 2019-12-3; first domain sponsor
                    if (isset($dresult['regrinfo']['domain']['sponsor'][0])) {
                        $registrar_owner = $dresult['regrinfo']['domain']['sponsor'][0];
                    } elseif (isset($dresult['regyinfo']['registrar'])) {
                        $registrar_owner = $dresult['regyinfo']['registrar'];
                    }
                    // country
                    if (isset($dresult['regrinfo']['domain']['sponsor'][3])) {
                        $registrar_country = $dresult['regrinfo']['domain']['sponsor'][3];
                    }

                    // abusecontact
                    if (isset($dresult['regrinfo']['domain']['sponsor'][6])) {
                        // check if enough data in array
                        if (isset($dresult['regrinfo']['domain']['sponsor'][10])) {
                            $registrar_abusecontact = $dresult['regrinfo']['domain']['sponsor'][6];
                        }
                    }

                    // obsolute; must be found above - cannot use rawdata, each time different formats
                    /*
                    foreach ($dresult['rawdata'] AS $key => $value) {
                        $value = trim($value);
                        $pos = stripos($value, self::$_dm_registrar_abuse_contact_str);
                        //scartLog::logLine("D-rawdata; pos=$pos; [$key]=$value");
                        if ($pos !== false) {
                            $registrar_abusecontact = trim(substr($value, strlen(self::$_dm_registrar_abuse_contact_str) + 1));

                        }
                        $pos = stripos($value, self::$_dm_registrar_country_str);
                        if ($pos !== false) {
                            $registrar_country = trim(substr($value, strlen(self::$_dm_registrar_country_str) + 1));
                        }
                    }
                    */

                    // sometimes list of names
                    $registrar_owner = (is_array($registrar_owner)) ? implode(' ', $registrar_owner) : $registrar_owner;
                    $registrar_abusecontact = (is_array($registrar_abusecontact)) ? implode(' ', $registrar_abusecontact) : $registrar_abusecontact;
                    $registrar_country = (is_array($registrar_country)) ? implode(' ', $registrar_country) : $registrar_country;

                    $domain_rawtext .= "\nDOMAIN LOOKUP\n\n";
                    $domain_rawtext .= implode("\n", $dresult['rawdata']) . "\n";

                    $status_success = true;
                    $status_text = "WhoIs lookup succeeded";

                } else {
                    $status_text = 'Warning WhoIs lookup; input parameter host not set ';
                    scartLog::logLine("E-scartWhoisphpWhois; " . $status_text);
                }

            } else {
                $status_text = "Warning url_base with NO maindomain!? (host=$host) ";
                scartLog::logLine('E-scartWhoisphpWhois; '.$status_text);
            }

        } catch (\Exception $err) {
            scartLog::logLine("E-scartWhoisphpWhois.lookupDomain; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            $status_text = "Warning lookuplink: " . $err->getMessage();
        }

        return [
            'status_success' => $status_success,
            'status_text' => $status_text,
            'registrar_lookup' => $maindomain,
            'registrar_owner' => $registrar_owner,
            'registrar_abusecontact' => $registrar_abusecontact,
            'registrar_country' => $registrar_country,
            'registrar_rawtext' => $domain_rawtext,
            'dresult' => $dresult,
        ];
    }

}
