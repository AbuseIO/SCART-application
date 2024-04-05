<?php

namespace abuseio\scart\classes\iccam\api3\models;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\Controllers\Grade;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Iccam_api_field;

class scartICCAMfieldsV3 {

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

    public static $actionMapV2 = [
        1 => 'LEA',
        2 => 'ISP',
        3 => 'Content Removed (CR)',
        4 => 'Content Unavailable (CU)',
        5 => 'Moved (MO)',
        7 => 'Not Illegal (NI)',
    ];
    // scart_code -> iccam id
    public static function getActionID($actionID) {

        $iccamfield = Iccam_api_field::where('scart_field','actionID')
            ->where('scart_code',$actionID)
            ->first();
        return ($iccamfield) ? $iccamfield->iccam_id : 0;
    }
    // iccam id -> scart_code
    public static function getICCAMactionID($ICCAMactionID) {

        $iccamfield = Iccam_api_field::where('scart_field','actionID')
            ->where('iccam_id',$ICCAMactionID)
            ->first();
        return ($iccamfield) ? $iccamfield->scart_code : 0;
    }
    // scart_code -> iccam name
    public static function getActionName($actionID) {

        $iccamfield = Iccam_api_field::where('scart_field','actionID')
            ->where('scart_code',$actionID)
            ->first();
        return ($iccamfield) ? $iccamfield->iccam_name : '(unknown)';
    }

    // actionReasons; iccam_name -> iccam id
    public static function getActionReasonID($reason) {

        $iccamfield = Iccam_api_field::where('scart_field','actionReasonID')
            ->where('iccam_name',$reason)
            ->first();
        // note: if not found then "Other"
        return ($iccamfield) ? $iccamfield->iccam_id : 1;
    }

    // SiteTypeID V2
    public static $SiteTypeIDMapV2 = [
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
        'notapplicable' => 24,
    ];
    // scart type_code -> iccam_id
    // default=website
    public static $siteTypeIDNotDetermined = 1;
    public static $siteTypeIDWebsite = 2;
    public static function getSiteTypeID($record,$default='') {

        if ($default=='') $default = self::$siteTypeIDWebsite;  // $siteTypeIDNotDetermined not supported (anymore)
        $iccamfield = Iccam_api_field::where('scart_field','SiteTypeID')
            ->where('scart_code',$record->type_code)
            ->first();
        return ($iccamfield) ? $iccamfield->iccam_id : $default;
    }
    // iccam_id -> scart type_code
    public static $siteTypeNotDetermined = 'notdetermined';
    public static function getSiteType($siteTypeId) {

        $siteTypeId = (is_numeric($siteTypeId)) ? intval($siteTypeId) : 1;
        $iccamfield = Iccam_api_field::where('scart_field','SiteTypeID')
            ->where('iccam_id',$siteTypeId)
            ->first();
        return ($iccamfield) ? $iccamfield->scart_code : self::$siteTypeNotDetermined;
    }

    // ClassificationID
    public static $ClassificationIDMapV2 = [
        'BA' => 1,                // Baseline SCAM
        'NA' => 2,                // National SCAM
        'DO' => 3,                // Doubtful
        'IG' => 4,                // Ignore
    ];
    public static function getClassificationIDOptions() {

        $options = Iccam_api_field::where('scart_field','ClassificationID')->get();
        $ret = [];
        foreach ($options as $option) {
            if ($option->scart_code != 'IG') {
                $ret[$option->scart_code] = $option->iccam_name;
            }
        }
        return $ret;
    }
    public static $ClassificationNotIllegal = 'IG';     // Not illegal (ignore)
    public static $ClassificationIDNotDetermined = 1;   // fallback to Baseline
    public static $ClassificationIDbaseline = 1;   // fallback to Baseline
    public static $ClassificationIDignore = 4;   // fallback to Ignore
    public static function getClassificationID($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        $answer = ($answer) ? implode('',$answer) : '';
        $iccamfield = Iccam_api_field::where('scart_field','ClassificationID')
            ->where('scart_code',$answer)
            ->first();
        return ($iccamfield) ? $iccamfield->iccam_id : self::$ClassificationIDNotDetermined;
    }

    // GenderID
    public static $GenderIDMapV2 = [
        'UN' => 1,          // Undetermined
        'FE'=> 2,           // Female
        'MA' => 3,          // Male
        'BO' => 4,          // Both
        'FEMA' => 4,        // Both
    ];
    public static function getGenderIDOptions() {

        $options = Iccam_api_field::where('scart_field','GenderID')->get();
        $ret = [];
        foreach ($options as $option) {
            if ($option->scart_code != 'FEMA') {
                $ret[$option->scart_code] = $option->iccam_name;
            }
        }
        return $ret;
    }
    public static $GenderIDNotDetermined = 1;
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
        $iccamfield = Iccam_api_field::where('scart_field','GenderID')
            ->where('scart_code',$gender)
            ->first();
        $genderID = ($iccamfield) ? $iccamfield->iccam_id : self::$GenderIDNotDetermined;
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=".implode('/',$answer).", gender=$gender, genderID=$genderID ");
        return $genderID;
    }

    // AgeGroupID
    public static $AgeGroupIDMapV2 = [
        'ND' => 1,          // Not Determined
        'IN' => 2,          // Infant
        'PP' => 3,          // Pre-pubescent
        'PU' => 4,          // Pubescent
    ];
    public static function getAgeGroupIDOptions() {

        $options = Iccam_api_field::where('scart_field','AgeGroupID')->get();
        $ret = [];
        foreach ($options as $option) {
            $ret[$option->scart_code] = $option->iccam_name;
        }
        return $ret;
    }
    public static $AgeGroupIDNotDetermined = 1;
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
        $iccamfield = Iccam_api_field::where('scart_field','AgeGroupID')
            ->where('scart_code',$age)
            ->first();
        $ageID = ($iccamfield) ? $iccamfield->iccam_id : self::$AgeGroupIDNotDetermined;
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=".implode('/',$answerAge).", age=$age, ageID=$ageID ");
        return $ageID;
    }

    // CommercialityID
    public static $CommercialityIDMapV2 = [
        1 => 'Not Determined',
        2 => 'Commercial',
        3 => 'Non-Commercial',
    ];
    public static function getCommercialityIDOptions() {

        $options = Iccam_api_field::where('scart_field','CommercialityID')->get();
        $ret = [];
        foreach ($options as $option) {
            $ret[$option->scart_code] = $option->iccam_name;
        }
        return $ret;
    }
    public static $CommercialityIDNotDetermined = 1;
    public static function getCommercialityID($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        // if radio field, then array is return -> set on (first) array value
        if (is_array($answer)) $answer = (isset($answer[0])?$answer[0]:'');
        $iccamfield = Iccam_api_field::where('scart_field','CommercialityID')
            ->where('scart_code',$answer)
            ->first();
        $ID = ($iccamfield) ? $iccamfield->iccam_id : self::$CommercialityIDNotDetermined;
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=$answer, ID=$ID ");
        return $ID;
    }

    // PaymentMethodID
    public static $PaymentMethodIDMapV2 = [
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
        $options = Iccam_api_field::where('scart_field','PaymentMethodID')->get();
        $ret = [];
        foreach ($options as $option) {
            $ret[$option->scart_code] = $option->iccam_name;
        }
        return $ret;
    }
    public static $PaymentMethodIDNotDetermined = 1;
    public static function getPaymentMethodID($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        if (is_array($answer)) $answer = (isset($answer[0])?$answer[0]:'');
        $iccamfield = Iccam_api_field::where('scart_field','PaymentMethodID')
            ->where('scart_code',$answer)
            ->first();
        $ID = ($iccamfield) ? $iccamfield->iccam_id : self::$PaymentMethodIDNotDetermined;
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=$answer, ID=$ID ");
        return $ID;
    }

    // ContentType
    public static $ContentTypeMapV2 = [
        0 => 'Image',
        1 => 'Video',
        2 => 'Link',
        3 => 'Container',
        4 => 'Other',
        5 => 'Text',
    ];
    public static function getContentTypeOptions() {

        // Note: NO V3 of ContentType!?!
        return self::$ContentTypeMapV2;
    }
    public static function getContentType($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        if (is_array($answer)) $answer = (isset($answer[0])?$answer[0]:'');
        $ID = (array_key_exists($answer,self::$ContentTypeMapV2)) ? $answer : 4;  // image if not found
        scartLog::logLine("D-scartICCAMfields; field=$question->name; answer=$answer, ID=$ID ");
        return $ID;
    }

    // IsVirtual
    public static $IsVirtualMapV2 = [
        false => 'No',
        true => 'Yes',
    ];
    public static function getIsVirtualOptions() {
        $options = Iccam_api_field::where('scart_field','IsVirtual')->get();
        $ret = [];
        foreach ($options as $option) {
            $ret[$option->scart_code] = $option->iccam_name;
        }
        return $ret;
    }
    public static function getIsVirtual($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        $answer = (is_bool($answer)) ? $answer : false;
        $iccamfield = Iccam_api_field::where('scart_field','IsVirtual')
            ->where('scart_code',$answer)
            ->first();
        return ($iccamfield) ? $iccamfield->iccam_id : 0;
    }

    // IsChildModeling
    public static $IsChildModelingMapV2 = [
        false => 'No',
        true => 'Yes',
    ];
    public static function getIsChildModelingOptions() {
        $options = Iccam_api_field::where('scart_field','IsChildModeling')->get();
        $ret = [];
        foreach ($options as $option) {
            $ret[$option->scart_code] = $option->iccam_name;
        }
        return $ret;
    }
    public static function getIsChildModeling($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        $answer = (is_bool($answer)) ? $answer : false;
        $iccamfield = Iccam_api_field::where('scart_field','IsChildModeling')
            ->where('scart_code',$answer)
            ->first();
        return ($iccamfield) ? $iccamfield->iccam_id : 0;
    }

    // IsUserGC
    public static $IsUserGCMapV2 = [
        false => 'No',
        true => 'Yes',
    ];
    public static function getIsUserGCOptions() {
        $options = Iccam_api_field::where('scart_field','IsUserGC')->get();
        $ret = [];
        foreach ($options as $option) {
            $ret[$option->scart_code] = $option->iccam_name;
        }
        return $ret;
    }
    public static function getIsUserGC($question,$record) {

        $answer = Grade_question::getGradeAnswer($question->name,$record);
        $answer = (is_bool($answer)) ? $answer : false;
        $iccamfield = Iccam_api_field::where('scart_field','IsUserGC')
            ->where('scart_code',$answer)
            ->first();
        return ($iccamfield) ? $iccamfield->iccam_id : 0;
    }




    /** ICCAM field SETTER **/

    public static function setClassificationICCAMfield($question,$input,$value) {

        Grade_question::setGradeAnswer($question->name,$input,[$value]);
    }


}
