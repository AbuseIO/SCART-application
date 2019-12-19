<?php
namespace reportertool\eokm\classes;

use Config;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Blockedday;

class ertBlockeddays {

    private static $_blocked = [];

    public static function triggerNTDblocked() {

        $active = Config::get('reportertool.eokm::ntd.use_blockeddays', true);
        if ($active) {
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $hour = date('H');
            $dayofweek = date('N');

            if ($dayofweek == 6 || $dayofweek == 7) {
                $blocked = true;
            } elseif (Blockedday::where('day',$today)->count() > 0) {
                $blocked = true;
            } elseif (Blockedday::where('day',$yesterday)->count() > 0) {
                // check if before 12 -> then also blocked
                $after_blockedday_hours = Config::get('reportertool.eokm::ntd.after_blockedday_hours', 12);
                $blocked = ($hour < $after_blockedday_hours);
            } else {
                $blocked = false;
            }
            ertLog::logLine("D-ertBlockeddays; dayofweek=$dayofweek, today=$today, yesterday=$yesterday, hour=$hour, blocked=$blocked");
        } else {
            $blocked = false;
        }
        return $blocked;
    }



}
