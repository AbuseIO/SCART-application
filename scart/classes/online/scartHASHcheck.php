<?php
namespace abuseio\scart\classes\online;

use Config;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;

class scartHASHcheck {

    private static $_hashformat = '';
    private static $_hashurl = 'https://api.eokmhashdb.nl/v1/check/';
    private static $_hashurltest = 'https://api.eokmhashdb.nl/v1/test/check/';
    private static $_lasterror = '';

    public static function isActive() {
        $active = Systemconfig::get('abuseio.scart::hashapi.active', false);
        return $active;
    }

    public static function init() {
        if (self::$_hashformat=='') {
            self::$_hashformat = Systemconfig::get('abuseio.scart::hashapi.format', 'md5');
            scartLog::logLine("D-scartHASHcheck; format set on: " . self::$_hashformat);
        }
    }

    public static function getFormat() {
        return self::$_hashformat;
    }

    public static function inDatabase($data,$hash='',$testapi=false) {

        $indb = false;

        if (self::isActive()) {

            try {

                self::init();

                if ($hash=='') {
                    $hash = self::hash($data);
                }

                $username = Systemconfig::get('abuseio.scart::hashapi.username', '');
                $password = Systemconfig::get('abuseio.scart::hashapi.password', '');


                if (!$testapi) $testapi = Systemconfig::get('abuseio.scart::hashapi.test', false);
                $url = (($testapi) ? SELF::$_hashurltest : SELF::$_hashurl) . self::$_hashformat;

                scartLog::logLine("D-scartHASHcheck; hash=$hash, url=$url " . (($testapi)? '(TEST MODE)' : '') );

                $request = curl_init();

                $options = array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_USERPWD => $username . ":" . $password,
                    CURLOPT_SSL_VERIFYHOST => FALSE,
                    CURLOPT_SSL_VERIFYPEER => FALSE,
                    CURLOPT_FOLLOWLOCATION => TRUE,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $hash,
                    CURLOPT_MAXREDIRS => 10,);
                curl_setopt_array($request, $options);
                $response = curl_exec($request);
                if (curl_error($request)) {
                    SELF::$_lasterror = "response=$response; curl_error=".curl_error($request);
                    scartLog::logLine("W-scartHASHcheck; url=$url; error: ".SELF::$_lasterror );
                } else {
                    //scartLog::logLine("D-scartHASHcheck; url=$url; check($hash); response=" . $response );
                    $indb = ($response=='true');
                    SELF::$_lasterror = '';
                }
                curl_close($request);

            } catch (Exception $err) {

                SELF::$_lasterror = $err->getMessage();
                scartLog::logLine("W-scartHASHcheck; error (catched): ".SELF::$_lasterror );

            }

        }

        return $indb;
    }

    public static function hash($data) {

        switch (self::$_hashformat) {
            case 'md5':
                $hash = md5($data);
                break;

            case 'sha1':
                $hash = sha1($data);
                break;

            case 'photodna':
                scartLog::logLine("E-Not yet supported hash set; '".self::$_hashformat."' !?");
                $hash = '';
                break;

            default:
                scartLog::logLine("E-Unknown hash set; '".self::$_hashformat."' !?");
                $hash = '';
                break;

        }

        return $hash;
    }


    public static function getClassification() {

        // hardcode (rule based) settings array
        // @To-Do: make domainrule for FOUND_IN_HASHDATABASE so clasisfication can dynamic be modified
        $settings = [
            'type_code_illegal' => [
                'website'
            ],
            'grades' => [
                [
                    'grade_question_id' => 1,
                    'answer'=> [
                        'NA',
                    ],
                ],
                [
                    'grade_question_id' => 2,
                    'answer'=> [
                        'UN',
                    ]
                ],
                [
                    'grade_question_id' => 3,
                    'answer'=> [
                        'ND',
                    ]
                ],
            ],
            'police_first' => [
                'n',
            ],
        ];

        return $settings;
    }

}
