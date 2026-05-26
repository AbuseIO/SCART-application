<?php namespace abuseio\scart\classes\iccam\api3\classes\helpers;

use abuseio\scart\classes\helpers\scartLog;

class ICCAMerrors {

    private static $_ICCAMmessages = [
        SCART_ICCAM_ERROR_statusCompleted => 'Cannot action: content status Completed',
        SCART_ICCAM_ERROR_alreadyFinalAction => 'Content can not be actioned further, because it has already a final action',
        SCART_ICCAM_ERROR_statusToBeActioned => 'Cannot assess: content status ToBeActioned',
        SCART_ICCAM_ERROR_notStateForAssessments => 'Report not in correct state for assessments',
        SCART_ICCAM_ERROR_notRightPhaseForActions => 'Report not in the right phase to action',
        SCART_ICCAM_ERROR_tokenExpired => 'token expired',
        SCART_ICCAM_ERROR_tokenUnknown => 'Authorization bearer empty or unknown',
    ];
    private static $_ICCAMfatal = [
        SCART_ICCAM_ERROR_statusCompleted,
        SCART_ICCAM_ERROR_alreadyFinalAction,
        SCART_ICCAM_ERROR_statusToBeActioned,
        SCART_ICCAM_ERROR_unknown,
    ];
    private static $_ICCAMretry = [
        SCART_ICCAM_ERROR_notStateForAssessments,
        SCART_ICCAM_ERROR_notRightPhaseForActions,
        SCART_ICCAM_ERROR_tokenExpired,
    ];

    public static function getErrorCode($errorarr) {

        $constant = SCART_ICCAM_ERROR_unknown;
        // return first code found
        foreach ($errorarr as $arr) {
            $string = (isset($arr['string'])?$arr['string']:'?');
            if ($constant = array_search($string,ICCAMerrors::$_ICCAMmessages)) {
                break;
            }
        }
        //scartLog::logLine("D-ICCAMerrors.getErrorCode; return $constant");
        return $constant;
    }

    public static function isFatal($errorarr) {
        return in_array(ICCAMerrors::getErrorCode($errorarr),ICCAMerrors::$_ICCAMfatal);
    }

    public static function isRetry($errorarr) {
        return in_array(ICCAMerrors::getErrorCode($errorarr),ICCAMerrors::$_ICCAMretry);
    }


}
