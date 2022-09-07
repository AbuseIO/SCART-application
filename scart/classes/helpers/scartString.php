<?php

namespace abuseio\scart\classes\helpers;


use Config;
use BackendAuth;
use Illuminate\Support\Facades\Log;
use Mail;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Systemconfig;

class scartString {

    /**
     * @param $string
     * @param $start
     * @param $end
     * @return false|string
     */
    public static function get_strings_between($string, $start, $end, $result = []){

        // count character
        $loop = substr_count($string,$start) / 2;
        if ($loop > 0) {
            for ($x = 1; $x <= $loop; $x++) {
                $string = ' ' . $string;
                $ini = strpos($string, $start);
                if ($ini == 0) return '';
                $ini += strlen($start);
                $len = strpos($string, $end, $ini) - $ini;
                $word = substr($string, $ini, $len);

                $string = str_replace('#'.$word.'#', '', $string);
                $result[] = $word;
            }

        }
        return $result;
    }


}
