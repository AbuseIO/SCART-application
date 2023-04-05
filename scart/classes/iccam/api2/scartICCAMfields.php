<?php

namespace abuseio\scart\classes\iccam\api2;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\Controllers\Grade;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;

class scartICCAMfields {

    /** general vars **/

    public static $actionMap = [
        1 => 'LEA',
        2 => 'ISP',
        3 => 'Content Removed (CR)',
        4 => 'Content Unavailable (CU)',
        5 => 'Moved (MO)',
        7 => 'Not Illegal (NI)',
    ];

    public static $_classificationNotIllegal = 4;     // Not illegal (ignore)

    /** convert ICCAM date **/

    public static function iccamDate($time) {
        $d = date(DATE_ATOM, $time);
        $p = strpos($d,'+');
        if ($p!==false) {
            $d = substr($d , 0, $p) . 'Z';
        }
        return $d;
    }

    /** ICCAM get field functions **/

    // SiteTypeID
    public static $SiteTypeIDMap = [
        'notdetermined' => 1,
        'website' => 2,
        'filehost' => 3,
        'imagestore' => 4,
        'imageboard' => 5,
        'forum' => 6,
        'bannersite' => 7,
        'linksite' => 8,
        'socialsite' => 9,
        'redirector' => 10,
        'webarchived' => 11,
        'searchprovider' => 18,
        'imagehost' => 20,
        'blog' => 22,
        'webpage' => 23,
    ];
    public static $siteTypeIDNotDetermined = 1;
    public static function getSiteTypeID($record) {

        //return (isset(self::$SiteTypeIDMap[$record->type_code])) ? self::$SiteTypeIDMap[$record->type_code] : self::$SiteTypeIDMap['notdetermined'];
        return (array_key_exists($record->type_code,self::$SiteTypeIDMap)) ? self::$SiteTypeIDMap[$record->type_code] : self::$SiteTypeIDMap['notdetermined'];
    }
    public static function getSiteType($siteTypeId) {

        $siteTypeId = (is_numeric($siteTypeId)) ? intval($siteTypeId) : 1;
        $key = array_search($siteTypeId, scartICCAMfields::$SiteTypeIDMap);
        $type_code = ($key !== false) ? $key : SCART_ICCAM_IMPORT_TYPE_CODE_ICCAM;
        return $type_code;
    }

    // ClassificationID
    public static $ClassificationIDMap = [
        'BA' => 1,                // Baseline SCAM
        'NA' => 2,                // National SCAM
        'DO' => 3,                // Doubtful
        'IG' => 4,                // Ignore
    ];
    public static function getClassificationIDOptions() {
        // Note: not ignore
        return [
            'BA' => 'Baseline',    // Baseline SCAM
            'NA' => 'National',    // National SCAM
            'DO' => 'Doubtful',    // Doubtful
        ];
    }
    public static function getClassificationID($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        $answer = ($answer) ? implode('',$answer) : '';
        //return  (isset(self::$ClassificationIDMap[$answer])) ? self::$ClassificationIDMap[$answer] : self::$ClassificationIDMap['BA'];
        return  (array_key_exists($answer,self::$ClassificationIDMap)) ? self::$ClassificationIDMap[$answer] : self::$ClassificationIDMap['BA'];
    }

    // GenderID
    public static $GenderIDMap = [
        'UN' => 1,          // Undetermined
        'FE'=> 2,           // Female
        'MA' => 3,          // Male
        'BO' => 4,          // Both
        'FEMA' => 4,        // Both
    ];
    public static function getGenderIDOptions() {
        return  [
            'MA' => 'Male',            // Male
            'FE'=> 'Female',           // Female
            'UN' => 'Not Determined',  // Undetermined
            'BO'=> 'Both',           // both
        ];
    }
    public static function getGenderID($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        $gender = '';
        if (in_array('UN', $answer)) {
            $gender = 'UN';
        } else {
            if (in_array('FE', $answer)) {
                $gender .= 'FE';
            }
            if (in_array('MA', $answer)) {
                $gender .= 'MA';
            }
        }
        //$genderID = (isset(self::$GenderIDMap[$gender])) ? self::$GenderIDMap[$gender] : self::$GenderIDMap['UN'];
        $genderID = (array_key_exists($gender,self::$GenderIDMap)) ? self::$GenderIDMap[$gender] : self::$GenderIDMap['UN'];
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=".implode('/',$answer).", gender=$gender, genderID=$genderID ");
        return $genderID;
    }

    // AgeGroupID
    public static $AgeGroupIDMap = [
        'ND' => 1,          // Not Determined
        'IN' => 2,          // Infant
        'PP' => 3,          // Pre-pubescent
        'PU' => 4,          // Pubescent
    ];
    public static function getAgeGroupIDOptions() {
        return [
            'ND' => 'Not Determined',          // Not Determined
            'IN' => 'Infant',          // Infant
            'PP' => 'Pre-pubescent',          // Pre-pubescent
            'PU' => 'Pubescent',          // Pubescent
        ];
    }
    public static function getAgeGroupID($question,$record) {

        $answerAge = Grade_question::getGradeAnswer($question->name,$record);
        $age = '';
        if (in_array('IN', $answerAge)) {
            $age = 'IN';
        }
        if (in_array('PP', $answerAge)) {
            $age = 'PP';
        }
        if (in_array('PU', $answerAge)) {
            $age = 'PU';
        }
        //$ageID = (isset(self::$AgeGroupIDMap[$age])) ? self::$AgeGroupIDMap[$age] : self::$AgeGroupIDMap['ND'];
        $ageID = (array_key_exists($age,self::$AgeGroupIDMap)) ? self::$AgeGroupIDMap[$age] : self::$AgeGroupIDMap['ND'];
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=".implode('/',$answerAge).", age=$age, ageID=$ageID ");
        return $ageID;
    }

    /** dec-2021 NEW ICCAM fields - other (better) setup **/

    // CommercialityID
    public static $CommercialityIDMap = [
        1 => 'Not Determined',
        2 => 'Commercial',
        3 => 'Non-Commercial',
    ];
    public static function getCommercialityIDOptions() {
        return self::$CommercialityIDMap;
    }
    public static function getCommercialityID($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        // if radio field, then array is return -> set on (first) array value
        if (is_array($answer)) $answer = (isset($answer[0])?$answer[0]:'');
        //$ID = (isset(self::$CommercialityIDMap[$answer])) ? $answer : 1;
        $ID = (array_key_exists($answer,self::$CommercialityIDMap)) ? $answer : 1;
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=".self::$CommercialityIDMap[$ID].", ID=$ID ");
        return $ID;
    }

    // PaymentMethodID
    public static $PaymentMethodIDMap = [
        1 => 'Not Determined',
        2 => 'AMEX',
        3 => 'Diners',
        4 => 'Mastercard',
        5 => 'Paypal',
        6 => 'Visa',
        7 => 'Western Union',
        8 => 'Other',
        9 => 'None',
        10 => 'SMS',
        11 => 'EMAIL',
        12 => 'Liberty Reserve',
        13 => 'Bitcoin',
    ];
    public static function getPaymentMethodIDOptions() {
        return self::$PaymentMethodIDMap;
    }
    public static function getPaymentMethodID($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        if (is_array($answer)) $answer = (isset($answer[0])?$answer[0]:'');
        //$ID = (isset(self::$PaymentMethodIDMap[$answer])) ? $answer : 1;
        $ID = (array_key_exists($answer,self::$PaymentMethodIDMap)) ? $answer : 1;
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=".self::$PaymentMethodIDMap[$ID].", ID=$ID ");
        return $ID;
    }

    // ContentType
    public static $ContentTypeMap = [
        0 => 'Image',
        1 => 'Video',
        2 => 'Link',
        3 => 'Container',
        4 => 'Other',
        5 => 'Text',
    ];
    public static function getContentTypeOptions() {
        return self::$ContentTypeMap;
    }
    public static function getContentType($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        if (is_array($answer)) $answer = (isset($answer[0])?$answer[0]:'');
        //$ID = (isset(self::$ContentTypeMap[$answer])) ? $answer : 4;  // image if not found
        $ID = (array_key_exists($answer,self::$ContentTypeMap)) ? $answer : 4;  // image if not found
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=$answer, ID=$ID ");
        return $ID;
    }

    // IsVirtual
    public static $IsVirtualMap = [
        false => 'No',
        true => 'Yes',
    ];
    public static function getIsVirtualOptions() {
        return self::$IsVirtualMap;
    }
    public static function getIsVirtual($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        return (is_bool($answer)) ? $answer : false;
    }

    // IsChildModeling
    public static $IsChildModelingMap = [
        false => 'No',
        true => 'Yes',
    ];
    public static function getIsChildModelingOptions() {
        return self::$IsChildModelingMap;
    }
    public static function getIsChildModeling($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        return (is_bool($answer)) ? $answer : false;
    }

    // IsUserGC
    public static $IsUserGCMap = [
        false => 'No',
        true => 'Yes',
    ];
    public static function getIsUserGCOptions() {
        return self::$IsUserGCMap;
    }
    public static function getIsUserGC($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        return (is_bool($answer)) ? $answer : false;
    }

    /** ICCAM field SETTER **/

    public static function setClassificationICCAMfield($question,$input,$value) {

        Grade_question::setGradeAnswer($question->name,$input,[$value]);
    }


}
