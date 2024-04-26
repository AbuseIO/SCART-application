<?php
namespace abuseio\scart\classes\whois;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\browse\scartCURLcalls;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\mail\scartAlerts;

class scartRIPEdb extends scartCURLcalls {

    static private $_debug = false;

    static private $_ipwhois = 'https://stat.ripe.net/data/whois/data.json?sourceapp=abuseio-scart&resource=';
    static private $_ipaswhois = 'https://stat.ripe.net/data/as-overview/data.json?sourceapp=abuseio-scart&resource=';
    static private $_ipabuse = 'https://stat.ripe.net/data/abuse-contact-finder/data.json?sourceapp=abuseio-scart&resource=';

    static private $_cachedholder = [];
    static private $_cachedabuse = [];

    static private $_errorretry = 6;

    public static function resetCache() {

        $_cachedholder = [];
        $_cachedabuse = [];
    }

    private static function getCurl($urlprefix, $resource) {

        $url = $urlprefix . $resource;

        $response = self::call($url);

        if (self::hasError()) {

            $error = self::getError();
            scartAlerts::alertAdminStatus('RIPE_CURL_ERROR','scartRIPEdb.getCurl', true, $error, 3 );

        } else {

            if (self::$_debug) scartLog::logLine("D-scartRIPEdb.getCurl; calling $url: response: " . print_r($response,true) );
            scartAlerts::alertAdminStatus('RIPE_CURL_ERROR','scartRIPEdb.getCurl', false);

        }

        self::close();

        return $response;
    }

    // note: not used yet
    public static function getASholder($ASN) {

        $ASholder = '';

        if (isset(self::$_cachedholder[$ASN])) {

            $ASholder = self::$_cachedholder[$ASN];

        } else {

            $response = self::getCurl(self::$_ipaswhois,$ASN);
            if ($response) {
                $json = json_decode($response);
                if (isset($json->status) && $json->status == 'ok') {
                    $ASholder = isset($json->data->holder) ? $json->data->holder : '';
                    if ($ASholder) self::$_cachedholder[$ASN] = $ASholder;
                }
            }

        }

        return $ASholder;
    }

    public static function getIPabuse($resource) {

        $abusecontact = '';

        if (isset(self::$_cachedabuse[$resource])) {

            $abusecontact = self::$_cachedabuse[$resource];

        } else {

            $response = self::getCurl(self::$_ipabuse,$resource);
            if ($response) {
                $json = json_decode($response);
                // get (old) abuse_c
                if (isset($json->status) && $json->status == 'ok' && isset($json->data->anti_abuse_contacts->abuse_c)) {
                    $abusedata = $json->data->anti_abuse_contacts->abuse_c;
                    if (isset($abusedata[0]->email)) {
                        $abusecontact = $abusedata[0]->email;
                    }
                }
                // 2021/11/8/Gs; new version
                if (isset($json->status) && $json->status == 'ok' && isset($json->data->abuse_contacts[0])) {
                    $abusecontact = $json->data->abuse_contacts[0];
                }
                if ($abusecontact) self::$_cachedabuse[$resource] = $abusecontact;
            }

        }

        return $abusecontact;
    }

    public static function getIPcontact($IP) {

        $result = '';

        $response = self::getCurl(self::$_ipwhois,$IP);

        if ($response) {

            $json = json_decode($response);
            if (isset($json->status) && $json->status == 'ok') {

                $contactdata = [];

                // get all record keys into one array
                foreach ($json->data->records AS $record) {
                    foreach ($record AS $recordvalues) {
                        $contactdata[strtolower($recordvalues->key)] = $recordvalues->value;
                    }
                }
                // also irr records if found
                if (isset($json->data->irr_records[0]))  {
                    foreach ($json->data->irr_records AS $record) {
                        foreach ($record AS $recordvalues) {
                            $contactdata[strtolower($recordvalues->key)] = $recordvalues->value;
                        }
                    }
                }
                if (self::$_debug) scartLog::logLine("D-getIPcontact; contactdata=".print_r($contactdata,true) );

                // first check ASN holder
                $asn = (isset($contactdata['origin'])) ? 'AS'.$contactdata['origin'] : '';
                $host_owner = ($asn) ? self::getASholder($asn) : '';

                // failback on netname
                if (!$host_owner) {
                    $host_owner = (isset($contactdata['netname'])) ? $contactdata['netname'] : '';
                    if (!$host_owner) $host_owner = (isset($contactdata['owner'])) ? $contactdata['owner'] : '';
                }

                // get country (if set)
                $country = (isset($contactdata['country'])) ? $contactdata['country'] : '??';

                $result = [
                    'host_owner' =>  $host_owner,
                    'host_country' =>  $country,
                    'host_asn' => $asn,
                ];

            }

        }

        return $result;
    }


    public static function getIPinfo($IP) {

        $result =[
            'status_success' => true,
            'status_text' => 'RIPE lookup success',
            'host_lookup' => $IP,
            'host_owner' => '',
            'host_country' =>  '',
            'host_abusecontact' => '',
            'host_asn' => '',
            'host_rawtext' => 'Direct RIPE database query',
        ];
        $contact = self::getIPcontact($IP);
        if ($contact) {
            // merge results
            $result = array_merge($result,$contact);
            // get abusecontact for IP
            $abuse = self::getIPabuse($IP);
            if ($abuse) {
                $result['host_abusecontact'] = $abuse;
            } else {
                // if not found, then try abusecontact for ASN
                if ($result['host_asn']) {
                    $abuse = self::getIPabuse($result['host_asn']);
                    if ($abuse) {
                        $result['host_abusecontact'] = $abuse;
                    }
                }
            }
        }

        if (self::hasError()) {
            $result['status_success'] = false;
            $result['status_text'] = self::getError();
        }

        scartLog::logLine("D-getIPinfo; RIPE database query for IP=$IP; status_text=".$result['status_text']);
        //trace_log($result);
        return $result;
    }


}
