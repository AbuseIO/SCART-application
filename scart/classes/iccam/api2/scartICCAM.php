<?php

/**
 * ICCAM API class
 *
 * Basic functions
 * Helpers
 *
 */
namespace abuseio\scart\classes\iccam\api2;

use Config;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\mail\scartAlerts;

class scartICCAM {

    private static $_debug = false;

    private static $_channel = '';
    private static $_urlroot = '';
    private static $_cookie = '';
    private static $_loggedin = false;

    private static $_curltimeout = 10;
    private static $_curlerror = false;
    private static $_curlerrorretry = 3;

    public static function connect()
    {

        if (self::$_channel == '') {
            self::$_cookie = Systemconfig::get('abuseio.scart::iccam.cookie', '');
            if (!self::$_cookie) self::$_cookie = temp_path() . '/iccam.txt';
            self::$_urlroot = Systemconfig::get('abuseio.scart::iccam.urlroot', '');
            self::$_channel = curl_init();
        }
    }

    public static function init($url)
    {
        curl_reset(self::$_channel);            // needed, else 'hanging'
        curl_setopt(self::$_channel, CURLOPT_URL, $url);
        curl_setopt(self::$_channel, CURLOPT_PORT, '443');
        curl_setopt(self::$_channel, CURLOPT_CAINFO, Systemconfig::get('abuseio.scart::iccam.cacert', ''));
        //curl_setopt(self::$_channel, CURLOPT_CAPATH, Systemconfig::get('abuseio.scart::iccam.cacert', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLCERT, Systemconfig::get('abuseio.scart::iccam.sslcert', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLCERTPASSWD, Systemconfig::get('abuseio.scart::iccam.sslcertpw', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLKEY, Systemconfig::get('abuseio.scart::iccam.sslkey', ''));
        curl_setopt(self::$_channel, CURLOPT_SSLKEYPASSWD, Systemconfig::get('abuseio.scart::iccam.sslkeypw', ''));
        curl_setopt(self::$_channel, CURLOPT_SSL_VERIFYPEER, Systemconfig::get('abuseio.scart::iccam.verifypeer', true));
        //curl_setopt(self::$_channel, CURLOPT_SSL_VERIFYHOST, true);
        curl_setopt(self::$_channel, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$_channel, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(self::$_channel, CURLOPT_COOKIESESSION, true);
        curl_setopt(self::$_channel, CURLOPT_COOKIEJAR, self::$_cookie);
        curl_setopt(self::$_channel, CURLOPT_COOKIEFILE, self::$_cookie);

        curl_setopt(self::$_channel, CURLOPT_CONNECTTIMEOUT, self::$_curltimeout);

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
        // if not loggedin or channel empty
        if (!self::$_loggedin || self::$_channel == '') {
            self::connect();
            $result = self::update('login', [
                'username' => Systemconfig::get('abuseio.scart::iccam.apiuser', ''),
                'password' => Systemconfig::get('abuseio.scart::iccam.apipass', ''),
            ]);
            self::$_loggedin = ($result !== false);
        }
        return self::$_loggedin;
    }

    static function curl_call() {

        $result = curl_exec(self::$_channel);

        // check always also http return code if result
        if ($result) {
            $info = curl_getinfo(self::$_channel);
            if (isset($info['http_code'])) {
                if ($info['http_code'] != '200') {
                    // set  error
                    $result = false;
                }
            }
        }

        if ($result === false) {

            self::$_curlerror = true;

            // ICCAM interface sometimes not available -> inform admin with one time message (after retry count)

            $error = curl_errno(self::$_channel) > 0 ? array("curl_error_" . curl_errno(self::$_channel) => curl_error(self::$_channel)) : curl_getinfo(self::$_channel);
            scartLog::logLine("W-scartICCAM; CURL read/update error: " . print_r($error, true));
            $sendalert = scartUsers::getGeneralOption('ICCAM_CURL_ERROR');
            if (empty($sendalert)) $sendalert = 1;
            $sendalert = intval($sendalert) + 1;
            // check retry country
            if ($sendalert == self::$_curlerrorretry) {
                // (ONE TIME) send admin CURL error
                $params = [
                    'reportname' => 'ICCAM INTERFACE ERROR; retry count=' . $sendalert,
                    'report_lines' => [
                        "CURL_ERROR=" . print_r($error, true)
                    ]
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
            }
            scartUsers::setGeneralOption('ICCAM_CURL_ERROR', $sendalert);

        } else {

            self::$_curlerror = false;

            $sendalert = scartUsers::getGeneralOption('ICCAM_CURL_ERROR');
            if ($sendalert!='') {
                // Reset if error was set
                if (intval($sendalert) >= self::$_curlerrorretry) {
                    // (ONE TIME) send admin reset error
                    $params = [
                        'reportname' => 'ICCAM CURL IS WORKING (AGAIN); retry count='. $sendalert,
                        'report_lines' => [
                            "NO CURL_ERROR"
                        ]
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);
                }
                scartUsers::setGeneralOption('ICCAM_CURL_ERROR', '');
            }

        }
        return $result;
    }

    public static function isOffline() {
        return (self::$_curlerror);
    }

    public static function read($getaction)
    {

        $url = self::$_urlroot . $getaction;
        if (self::$_debug) scartLog::logLine("D-scartICCAM: GET url=$url");
        self::init($url);

        curl_setopt(self::$_channel, CURLOPT_HTTPGET, true);
        $result = self::curl_call();

        // decode json if postive result
        return ($result !== false) ? json_decode($result) : '';
    }

    public static function update($postaction, $data)
    {

        $url = self::$_urlroot . $postaction;
        if (self::$_debug) scartLog::logLine("D-scartICCAM: POST url=$url");
        self::init($url);

        $datajson = json_encode($data, JSON_FORCE_OBJECT);
        if (self::$_debug) scartLog::logLine("D-scartICCAM: POST json=$datajson");

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
        $result = self::curl_call();

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

    public static function insscartICCAM($data) {

        $hotlineID = Systemconfig::get('abuseio.scart::iccam.hotlineid', '');

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

        if (self::$_debug) scartLog::logLine("D-insscartICCAM; " . print_r($reportinsert, true));

        $result = self::update('SubmitLegacyReport', $reportinsert);
        //scartLog::logLine("D-scartICCAM; SubmitLegacyReport result: " . print_r($result, true));
        return $result;
    }

    /**
     *
     *
     *
     * @param $data
     * @return bool|mixed|string
     */

    public static function updscartICCAM($data) {

        $hotlineID = Systemconfig::get('abuseio.scart::iccam.hotlineid', '');

        // Merge data with main defaults
        $reportupdate = array_merge($data,[
            "HotlineID" => $hotlineID,
            "WebsiteName" => null,
            "PageTitle" => null,
            "Username" => null,
            "Password" => null,
            "PaymentMethodID" => null,
            "CommercialityID" => 1,                 // 1=Not Determined
            "ContentType" => 0,                     // 0=image
            "EthnicityID" => 1,
        ]);

        if (self::$_debug) scartLog::logLine("D-updscartICCAM; " . print_r($reportupdate, true));

        $result = self::update('SubmitReportUpdate', $reportupdate);
        //scartLog::logLine("D-scartICCAM; SubmitReportUpdate result: " . print_r($result, true));
        return $result;
    }

    public static function readICCAM($reportID) {

        $result = self::read('GetReports?id='.$reportID);
        return $result;
    }

    // postaction=SubmitItemUpdate ->



    /**
     * ActionID
     *  1: LEA
     *  2: ISP
     *  3: CR  Content Removed
     *  4: CU  Content Unavaiable
     *  5: MO  Moved
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
        //scartLog::logLine("D-scartICCAM insertActionICCAM; reportID=$reportID, actionID=$actionID; SubmitReportUpdate json: " . print_r($resultupdate, true));
        $result = self::update('SubmitReportUpdate', $resultupdate);
        return $result;
    }

    public static function readActionsICCAM($reportID) {

        $result = self::read('GetReportUpdates?id=' . $reportID);
        //scartLog::logLine("D-scartICCAM GetReportUpdates reportID=$reportID; result: " . print_r($result, true));
        return $result;
    }

    public static function getActionsICCAM($reportID) {

        $actions = '';
        // don't forget to login
        if (scartICCAM::login()) {
            $actions = scartICCAM::readActionsICCAM($reportID);
            scartICCAM::close();
        }
        return $actions;
    }

    public static function insertItemActionICCAM($itemID, $actionID, $data) {

        $resultupdate = array_merge($data,[
            'ObjectID' => $itemID,
            'ActionID' => $actionID,
        ]);
        //scartLog::logLine("D-scartICCAM     public static function insertItemActionICCAM($reportID, $actionID, $data) {; reportID=$reportID, actionID=$actionID; SubmitReportUpdate json: " . print_r($resultupdate, true));
        $result = self::update('SubmitItemUpdate', $resultupdate);
        return $result;
    }

    public static function readItemActionsICCAM($itemID) {

        $result = self::read('GetItemUpdates?id=' . $itemID);
        return $result;
    }

}
