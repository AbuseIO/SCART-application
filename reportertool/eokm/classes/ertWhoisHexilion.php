<?php

/**
 * getting WhoIs information from Hexilion
 *
 * main function lookup(link)
 *
 *   config
 *      auth_url
 *      auth_user
 *      auth_pass
 *      whois_url
 *
 *   link
 *     <domain>
 *
 *   output
 *      registrar_owner
 *      registrar_abusecontact
 *      registrar_customcontact
 *      host_owner
 *      host_abusecontact
 *      host_customcontact
 *      rawtext
 *
 */

namespace reportertool\eokm\classes;

use League\Flysystem\Exception;
use System\Helpers\DateTime;
use Config;
use Log;

class ertWhoisHexilion {

    private static $_sessionkey ='';
    private static $_sessiondate = '';

    private static $_options = array(
        CURLOPT_RETURNTRANSFER => true,   // return web page
        CURLOPT_HEADER         => false,  // don't return headers
        CURLOPT_FOLLOWLOCATION => true,   // follow redirects
        CURLOPT_MAXREDIRS      => 10,     // max redirects
        CURLOPT_ENCODING       => "",     // handle compressed
        CURLOPT_USERAGENT      => "ERT User agent", // name of client
        CURLOPT_AUTOREFERER    => true,   // set referrer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,    // time-out on connect
        CURLOPT_TIMEOUT        => 120,    // time-out on response
    );

    private static $_error = array();

    private static function auth() {

        $sessionkey = '';

        //ertLog::logLine("D-ertWhois.auth");

        // if session not set or outdated (>= 20 min) then renew authentification
        if (self::$_sessionkey=='' || self::$_sessiondate==='' || (date_diff(self::$_sessiondate, new DateTime('NOW'))->format('%i') >= 20)  ) {

            $url = Config::get('reportertool.eokm::whois.auth_url', '');
            if ($url) {

                try {

                    $ch = curl_init($url);
                    curl_setopt_array($ch, SELF::$_options);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, 'username='.Config::get('reportertool.eokm::whois.auth_user', '').'&password='.Config::get('reportertool.eokm::whois.auth_pass', ''));

                    //ertLog::logLine("D-ertWhois: authentification url=".$url);
                    $response  = curl_exec($ch);

                    if ($response===false) {

                        SELF::$_error['code'] = $error = curl_error($ch);
                        SELF::$_error['message'] = $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        ertLog::logLine("E-ertWhois: Error $error; HTTP_CODE=$info");

                    } else {

                        $access = new \SimpleXMLElement($response);

                        if (!empty($access->ErrorCode) && $access->ErrorCode == 'AuthenticationFailed') {

                            ertLog::logLine("E-ertWhois: code=$access->ErrorCode, message=$access->Message");

                        } else {

                            //ertLog::logLine("D-Login success");
                            $sessionkey = $access->SessionKey;

                        }

                    }

                    curl_close($ch);

                } catch (Exception $err) {

                    ertLog::logLine("E-ertWhois: catch error=".$err->getMessage());

                }

            } else {

                ertLog::logLine("E-ertWhois: url is empty - cannot autorize");

            }

        }

        if ($sessionkey) {
            self::$_sessiondate = new DateTime('NOW');
        }

        return $sessionkey;
    }

    public static function query($query) {

        ertLog::logLine("D-ertWhois.query($query)");

        $result = '';
        if ($sessionkey = SELF::auth()) {

            $url = Config::get('reportertool.eokm::whois.whois_url', '');

            if ($url) {

                try {

                    $url .= '?query='.urlencode($query).'&sessionkey='.urlencode($sessionkey);

                    $ch = curl_init($url);
                    curl_setopt_array($ch, SELF::$_options);
                    curl_setopt($ch, CURLOPT_POST, 0);  // GET
                    //curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: HexillionSession=$HexillionSession"));

                    $response  = curl_exec($ch);

                    if ($response===false) {

                        SELF::$_error['code'] = $error = curl_error($ch);
                        SELF::$_error['message'] = $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        ertLog::logLine("E-ertWhois: Error $error; HTTP_CODE=$info");

                    } else {

                        $result = new \SimpleXMLElement($response);
                        ertLog::logLine("D-Query request okay");

                        // @To-Do: $result->ErrorCode != 'Success'

                    }

                    curl_close($ch);

                } catch (Exception $err) {

                    ertLog::logLine("E-ertWhois: catch error=".$err->getMessage());

                }

            }

        }

        return $result;
    }

    /**
     * lookup host; extract WhoIs from host and from IP; return registrar and hosting owner/abuse info
     *
     * @param $host
     * @return array
     */

    public static function lookup($host,$returnresults=false) {

        // init output vars
        $registrar_owner = $registrar_abusecontact = $registrar_country = '';
        $host_owner = $host_abusecontact = $host_country = '';
        $status_success = false; $status_text = '';
        $domain_rawtext = $ip_rawtext = '';

        // extract main domain
        $arrhost = explode('.', $host);
        $maindomain = (count($arrhost) > 1) ? $arrhost[count($arrhost)-2] . '.' . $arrhost[count($arrhost)-1] : '';

        // input is base url from image
        if ($maindomain) {

            $dresult = self::query($maindomain);
            //trace_log($dresult);

            if ($dresult) {

                if (isset($dresult->QueryResult->WhoisRecord->Registrar->Name)) {
                    $registrar_owner = $dresult->QueryResult->WhoisRecord->Registrar->Name;
                } else {
                    $registrar_owner = '';
                }
                if (isset($dresult->QueryResult->WhoisRecord->AbuseContact->Email)) {
                    $registrar_abusecontact = $dresult->QueryResult->WhoisRecord->AbuseContact->Email;
                } else {
                    $registrar_abusecontact = '';
                }
                if (isset($dresult->QueryResult->WhoisRecord->Registrar->Country)) {
                    $registrar_country = $dresult->QueryResult->WhoisRecord->Registrar->Country;
                } else {
                    $registrar_country = '';
                }

                $domain_rawtext .= "<b>DOMAIN LOOKUP</b><br /><br />";
                $domain_rawtext .= $dresult->QueryResult->WhoisRecord->RawText;

                $ipurl = gethostbyname($host);
                $nresult = self::query($ipurl);
                //trace_log($nresult);

                if ($nresult) {

                    if (isset($nresult->QueryResult->WhoisRecord->Registrant->Name)) {
                        $host_owner = $nresult->QueryResult->WhoisRecord->Registrant->Name;
                    } else {
                        if (isset($nresult->QueryResult->WhoisRecord->AdminContact->Name)) {
                            $host_owner = $nresult->QueryResult->WhoisRecord->AdminContact->Name;
                        } else {
                            if (isset($nresult->QueryResult->WhoisRecord->TechContact->Name)) {
                                $host_owner = $nresult->QueryResult->WhoisRecord->TechContact->Name;
                            } else {
                                $host_owner = '(UNKNOWN?! - CHECK RAW DATA)';
                                ertLog::logLine("W-Host_owner=$host_owner ");
                                trace_log($nresult);
                            }
                        }
                    }
                    if (isset($nresult->QueryResult->WhoisRecord->AbuseContact->Email)) {
                        $host_abusecontact = $nresult->QueryResult->WhoisRecord->AbuseContact->Email;
                    } else {
                        // search comment
                        $host_abusecontact = $nresult->QueryResult->WhoisRecord->Network->Comment;
                        preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $host_abusecontact, $matches);
                        if (count($matches) > 0) {
                            if (count($matches[0]) > 0) {
                                // abuse email in comment string
                                $host_abusecontact = $matches[0][0];
                            }
                        }
                    }

                    // registrant country?
                    if (isset($nresult->QueryResult->WhoisRecord->Registrant->Country)) {
                        $host_country = $nresult->QueryResult->WhoisRecord->Registrant->Country;
                    } else {
                        // technical contact country?
                        if (isset($nresult->QueryResult->WhoisRecord->TechContact->Address[2])) {
                            $host_country = $nresult->QueryResult->WhoisRecord->TechContact->Address[2];
                        } else {
                            $host_country = '';
                        }
                    }

                    $ip_rawtext .= "<br /><b>NETWORK LOOKUP</b><br />";
                    $ip_rawtext .= $nresult->QueryResult->WhoisRecord->RawText;

                    $status_success = true;
                    $status_text = "WhoIs lookup (host=$host) succeeded";

                } else {
                    $status_text = 'Error WhoIs lookup from IP: ' .$ipurl;
                    ertLog::logLine("E-" . $status_text);
                }

            } else {
                $status_text = 'Error WhoIs lookup; input parameter host not set ';
                ertLog::logLine("E-" . $status_text);
            }


        } else {
            $status_text = 'Error url_base with NO maindomain!?!';
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
