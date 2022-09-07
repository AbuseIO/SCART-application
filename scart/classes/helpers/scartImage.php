<?php

namespace abuseio\scart\classes\helpers;

/**
 * scartLog
 *
 * Technical logging from action with code
 * Also sending error report
 *
 */

use Config;
use BackendAuth;
use Illuminate\Support\Facades\Log;
use Mail;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Systemconfig;

class scartImage {



    /**
     * Get img size (html) string based on size (db) from record
     *
     * @param $item
     * @param int $imgsize
     * @return string
     */
    public static function getImageSizeAttr($record,$imgsize=250,$resizebig=false,$values=false) {

        // 2px padding
        $imgreal = $imgsize - 4;
        $size = ''; $width = $height = 0;

        // check width and height and place in ratio
        if ($record->url_image_width > $imgsize && $record->url_image_height <= $imgsize) {
            $size = 'width="'.$imgreal.'" ';
            $width = $imgreal;
        } elseif ($record->url_image_width <= $imgsize && $record->url_image_height > $imgsize) {
            $size = 'height="'.$imgreal.'" ';
            $height = $imgreal;
        } elseif ($record->url_image_width > $imgsize && $record->url_image_height > $imgsize) {
            $size = 'height="'.$imgreal.'" width="'.$imgreal.'" ';
            $width = $height = $imgreal;
        } else {
            if ($resizebig) {
                // resize (zoom) on bigest dimension
                if ( $record->url_image_width > $record->url_image_height) {
                    $size = 'width="'.$imgreal.'" ';
                    $width = $imgreal;
                } else {
                    $size = 'height="'.$imgreal.'" ';
                    $height = $imgreal;
                }
            } else {
                $size = 'width="'.$record->url_image_width.'" ';
                $width = $record->url_image_width;
            }
        }
        if ($values) {
            $size = [$width,$height];
        } else {
            $size = (($width)?'width="'.$width.'" ':'') . (($height)?'height="'.$height.'" ':'');
        }
        //scartLog::logLine("D-getImageSizeAttr; imgsize=$imgsize, image width=$record->url_image_width, height=$record->url_image_height, size=$size");
        return $size;
    }

}
