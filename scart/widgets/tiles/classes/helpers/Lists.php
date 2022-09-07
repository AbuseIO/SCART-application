<?php
namespace abuseio\scart\widgets\tiles\classes\helpers;


use abuseio\scart\classes\classify\scartGrade;
use Illuminate\Support\Facades\Session;

class Lists {

    // MAIN RECORDS SESSION
    public static function  getListRecords() {
        $listrecords = Session::get('grade_listrecords','');
        if ($listrecords) $listrecords = unserialize($listrecords);
        if (empty($listrecords)) $listrecords = [0];
        return ($listrecords);
    }

}
