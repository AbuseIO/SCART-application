<?php

/**
 * ICCAM API class
 *
 * Basic functions
 * Helpers
 *
 */

namespace reportertool\eokm\classes;

use Config;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Grade_answer;
use ReporterTool\EOKM\Models\Grade_question;

class ertICCAM {

    private static $_debug = false;

    private static $_channel = '';
    private static $_urlroot = '';
    private static $_cookie = '';
    private static $_loggedin = false;

    public static function connect()
    {

        if (self::$_channel == '') {
            self::$_cookie = Config::get('reportertool.eokm::iccam.cookie', '');
            if (!self::$_cookie) self::$_cookie = temp_path() . '/iccam.txt';
            self::$_urlroot = Config::get('reportertool.eokm::iccam.urlroot', '');
            self::$_channel = curl_init();
        }
    }

    public static function init($url)
    {

        curl_reset(self::$_channel);            // needed, else 'hanging'
        curl_setopt(self::$_channel, CURLOPT_URL, $url);
        curl_setopt(self::$_channel, CURLOPT_PORT, '443');
        curl_setopt(self::$_channel, CURLOPT_SSLCERT, Config::get('reportertool.eokm::iccam.sslcert', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLCERTPASSWD, Config::get('reportertool.eokm::iccam.sslcertpw', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLKEY, Config::get('reportertool.eokm::iccam.sslkey', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLKEYPASSWD, Config::get('reportertool.eokm::iccam.sslkeypw', ''));
        curl_setopt(self::$_channel, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt(self::$_channel, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$_channel, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(self::$_channel, CURLOPT_COOKIESESSION, true);
        curl_setopt(self::$_channel, CURLOPT_COOKIEJAR, self::$_cookie);
        curl_setopt(self::$_channel, CURLOPT_COOKIEFILE, self::$_cookie);

    }

    public static function close()
    {
        if (self::$_channel != '') {
            curl_close(self::$_channel);
            self::$_channel = '';
            self::$_loggedin = false;
        }
    }

    public static function login()
    {

        if (!self::$_loggedin) {
            self::connect();
            $result = self::update('login', [
                'username' => Config::get('reportertool.eokm::iccam.apiuser', ''),
                'password' => Config::get('reportertool.eokm::iccam.apipass', ''),
            ]);
            self::$_loggedin = ($result !== false);
        }
        return self::$_loggedin;
    }

    public static function read($getaction)
    {

        $url = self::$_urlroot . $getaction;
        if (self::$_debug) ertLog::logLine("D-ertICCAM: GET url=$url");
        self::init($url);

        curl_setopt(self::$_channel, CURLOPT_HTTPGET, true);

        $result = curl_exec(self::$_channel);
        if ($result === false) {
            $error = curl_errno(self::$_channel) > 0 ? array("curl_error_" . curl_errno(self::$_channel) => curl_error(self::$_channel)) : curl_getinfo(self::$_channel);
            ertLog::logLine("E-ertICCAM; error login: " . print_r($error, true));
        }

        return ($result !== false) ? json_decode($result) : '';
    }

    public static function update($postaction, $data)
    {

        //self::$_debug = true;

        $url = self::$_urlroot . $postaction;
        if (self::$_debug) ertLog::logLine("D-ertICCAM: POST url=$url");
        self::init($url);

        $datajson = json_encode($data, JSON_FORCE_OBJECT);
        if (self::$_debug) ertLog::logLine("D-ertICCAM: POST json=$datajson");

        curl_setopt(self::$_channel, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt(self::$_channel, CURLOPT_POSTFIELDS, $datajson);

        curl_setopt(self::$_channel, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        curl_setopt(self::$_channel, CURLOPT_PRIVATE, true);
        curl_setopt(self::$_channel, CURLINFO_HEADER_OUT, true);

        curl_setopt(self::$_channel, CURLOPT_HEADEROPT, CURLHEADER_UNIFIED);
        curl_setopt(self::$_channel, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($datajson))
        );

        /*
         * // may be extra info?
        $f = fopen('request.txt', 'w');
        curl_setopt(self::$_channel, CURLOPT_STDERR,  $f );
        $result = curl_exec(self::$_channel);
        fclose($f);
        ertLog::logLine("D-ertICCAM: STDERRL: ". file_get_contents('request.txt') );
        */

        $result = curl_exec(self::$_channel);

        if ($result === false) {
            $error = curl_errno(self::$_channel) > 0 ? array("curl_error_" . curl_errno(self::$_channel) => curl_error(self::$_channel)) : curl_getinfo(self::$_channel);
            ertLog::logLine("E-ertICCAM; error POST: " . print_r($error, true));
        } else {
            $info = curl_getinfo(self::$_channel);
            if ($info['http_code'] != '200') {
                //ertLog::logLine("E-ertICCAM; result not 200; result: " . print_r($result, true));
                $info = print_r($info, true);
                ertLog::logLine("E-ertICCAM; result not 200; curl info: \n" . $info);
                ertLog::logLine("E-ertICCAM; update call; url=$url, post json=$datajson");
                $result = false;
            }
        }
        return $result;
    }

    /***************************************************************************

    /**  HELPERS **/

    /**
     * example data:
     *
     * 'Analyst' => 'dagmar@meldpunt-kinderporno.nl',
     * "Url" => "https://bit-" . date('YmdHms') . ".nl",
     * "HostingCountry" => "NL",
     * "HostingIP" => "185.46.65.12",
     * "HostingNetName" => "Network in Arnhem",
     * "Received" => "2019-10-22T08:15:00Z",
     * "ReportingHotlineReference" => "",  -> ERT: report#
     * "HostingHotlineReference" => "",  -> ERT: Hoster Abusecontact filenumber
     * 'Memo' => 'Add test memo on ' . date('Y-m-d H:i:s'),  -> ERT: note
     * "ClassifiedBy" => "dagmar@meldpunt-kinderporno.nl",
     * "Country" => "US",
     * "ClassificationDate" => "2019-10-22T10:18:00Z",
     * "SiteTypeID" => 2,
     *   1 Not Determined
     *   2 Website
     *   3 File host
     *   4 Image store
     *   5 Image board
     *   6 Forum
     *   7 Banner site
     *   8 Link site
     *   9 Social Networking
     *   10 Redirector
     *   11 Web archive
     *   18 Search provider
     *   20 Image host
     *   22 Blog
     *   23 Webpage
     * "GenderID" => 1,
     *   1 Not Determined
     *   2 Female
     *   3 Male
     *   4 Both
     * "AgeGroupID" => 1,
     *   1 Not Determined
     *   2 Infant
     *   3 Pre-pubescent
     *   4 Pubescent
     * "IsVirtual" => false,                   // Virtual
     * "IsChildModeling" => false,             // Sexualised Child Posing
     *
     *
     * @param $data
     * @return bool|string
     */

    public static function insertICCAM($data) {

        $hotlineID = Config::get('reportertool.eokm::iccam.HotlineID', '');

        // Merge data with ICCAM defaults
        $reportinsert = array_merge($data,[
            "HotlineID" => $hotlineID,
            "WebsiteName" => null,
            "PageTitle" => null,
            "Username" => null,
            "Password" => null,
            "PaymentMethodID" => null,
            "CommercialityID" => 1,                 // 1=Not Determined
            "ContentType" => 0,                     // 0=image
            "EthnicityID" => 1,
            "IsUserGC" => false,                    // User generated content
        ]);

        if (self::$_debug) ertLog::logLine("D-insertICCAM; " . print_r($reportinsert, true));

        $result = self::update('SubmitLegacyReport', $reportinsert);
        //ertLog::logLine("D-ertICCAM; SubmitLegacyReport result: " . print_r($result, true));
        return $result;
    }

    public static function readICCAM($reportID) {

        $result = self::read('GetReports?id='.$reportID);
        return $result;
    }

    /**
     * ActionID
     *  1: LEA
     *  2: ISP
     *  3: CR  Content Removed
     *  4: CU  Content Unavaiable
     *  5: MO  Moved
     *
     * data
     * 'Analyst' => "dagmar@meldpunt-kinderporno.nl",
     * 'Date' => "2019-10-14T08:15:00Z",
     * 'Country' => 'NL',
     * 'Reason' => "Illegal content",
     *
     * ActionID: 1+2;  mag na aanmaken
     * ActionID: 3+4; daarna mag niets meer
     *
     * @param $reportID
     * @param $actionID
     * @return bool|string
     */
    public static function insertActionICCAM($reportID, $actionID, $data) {

        $resultupdate = array_merge($data,[
            'ObjectID' => $reportID,
            'ActionID' => $actionID,
        ]);
        //ertLog::logLine("D-ertICCAM insertActionICCAM; reportID=$reportID, actionID=$actionID; SubmitReportUpdate json: " . print_r($resultupdate, true));
        $result = self::update('SubmitReportUpdate', $resultupdate);
        return $result;
    }

    public static function readActionsICCAM($reportID) {

        $result = self::read('GetReportUpdates?id=' . $reportID);
        //ertLog::logLine("D-ertICCAM GetReportUpdates reportID=$reportID; result: " . print_r($result, true));
        return $result;
    }

}
