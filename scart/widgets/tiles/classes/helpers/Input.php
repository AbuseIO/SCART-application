<?php
namespace abuseio\scart\widgets\tiles\classes\helpers;


use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartUsers;

class Input {

    public static function  getSortField() {
        $sort = scartUsers::getOption(SCART_USER_OPTION_SORTRECORDS);
        if ($sort=='') $sort = 'filenumber';
        return $sort;
    }

}
