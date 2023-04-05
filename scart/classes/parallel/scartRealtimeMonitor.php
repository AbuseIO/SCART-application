<?php
namespace abuseio\scart\classes\parallel;

use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\online\scartCheckOnline;
use abuseio\scart\classes\parallel\scartRealtimeCheckonline;
use abuseio\scart\classes\scheduler\scartSchedulerCheckOnline;
use abuseio\scart\models\Systemconfig;

class scartRealtimeMonitor {

    public static function realtimeStatus() {

        $realtimests = [];

        scartRealtimeCheckonline::initName();

        $lookagain = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120);

        $lastseen = scartSchedulerCheckOnline::lastseen();
        $lastseen = ($lastseen) ? $lastseen->lastseen_at : '';
        $lastseenago = ($lastseen) ? (time() - strtotime($lastseen)) : 0;
        $warning = ($lastseenago >= ($lookagain * 60));
        $realtimests[] = [
            'status' => 'oldest lastseen of normal/firsttime records',
            'count' => "$lastseen",
            'icon' => (!$warning) ? 'success' : 'warning',
        ];
        $realtimests[] = [
            'status' => 'oldest time ago (limit '.($lookagain).' minutes)',
            'count' => round($lastseenago / 60,0).' minutes',
            'icon' => (!$warning) ? 'success' : 'warning',
        ];
        if ($warning) {
            $lookagaintime = date('Y-m-d H:i:s', strtotime("-$lookagain minutes"));
            $realtimests[] = [
                'status' => 'current oldest lookagain time',
                'count' => $lookagaintime,
                'icon' => 'warning',
            ];
            $lastseencnt = scartSchedulerCheckOnline::lastseenCount($lookagaintime);
            $realtimests[] = [
                'status' => 'number of old lastseen records',
                'count' => $lastseencnt,
                'icon' => 'warning',
            ];
        }

        $check_online_every = Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',15);
        $realtimests[] = [
            'status' => 'check each report (url) not sooner then',
            'count' => $check_online_every.' minutes',
            'icon' => '',
        ];

        $realtimests[] = [
            'status' => 'check each report (url) within',
            'count' => $lookagain.' minutes',
            'icon' => '',
        ];

        $avg = scartSchedulerCheckOnline::checkAvgTime();
        $realtimests[] = [
            'status' => 'avg checkonline time (WhoIs & browser)',
            'count' => round($avg,2).' sec',
            'icon' => '',
        ];
        $max = scartSchedulerCheckOnline::checkMaxTime();
        $min = scartSchedulerCheckOnline::checkMinTime();
        $realtimests[] = [
            'status' => 'max/min checkonline time (WhoIs & browser)',
            'count' => round($max,2).'/'.round($min,2).' sec',
            'icon' => '',
        ];

        $countFirstTime = scartSchedulerCheckOnline::Firsttime()->count();
        $countRetry = scartSchedulerCheckOnline::Retry()->count();
        $countNormal = scartSchedulerCheckOnline::Normal($check_online_every)->count();
        $countfrn = $countFirstTime+ $countRetry + $countNormal;

        $counttot = scartSchedulerCheckOnline::countWork();
        $today = date('Y-m-d 23:59:59');
        $countlocks = scartCheckOnline::checkLocks($today);

        $look_again = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120);

        $inputs_max = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_inputs_max',10);
        $records_in_one_minute = scartRealtimeCheckonline::calculateRecordsEachMinute();
        $realtimests[] = [
            'status' => 'worker max records within one minute',
            'count' => $records_in_one_minute,
            'icon' => '',
        ];

        $realtimests[] = [
            'status' => 'minutes before worker spinning down again',
            'count' => Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_min_diff_spindown',15),
            'icon' => '',
        ];

        $createruntimes = [
            'FirstTime' => 1,
            'Retry' => 1,
            'Normal' => 1,
        ];

        $oldlockrecords = scartCheckOnline::getOldLocks($today);
        $locked = ['Retry' => 0, 'FirstTime' => 0, 'Normal' => 0];
        foreach ($oldlockrecords AS $oldlockrecord) {
            if ($oldlockrecord->browse_error_retry > 0) {
                $locked['Retry'] += 1;
            } elseif ($oldlockrecord->online_counter == 0) {
                $locked['FirstTime'] += 1;
            } else {
                $locked['Normal'] += 1;
            }
        }

        $threat_todo = [];
        foreach ($createruntimes AS $runtimename => $runtimeneeded) {

            $stdavg = scartSchedulerCheckOnline::checkStddevTime($runtimename);
            $realtimests[] = [
                'status' => "$runtimename; stddev time (WhoIs & browser)",
                'count' => round($stdavg,2).' sec',
                'icon' => '',
            ];

            $threat_todo[$runtimename] = scartRealtimeCheckonline::calculateThreatTodo($runtimename);
            $realtimests[] = [
                'status' => "$runtimename; worker capacity within look again time",
                'count' => $threat_todo[$runtimename],
                'icon' => '',
            ];

            $count = scartSchedulerCheckOnline::countWork($runtimename);
            $taskneeded = intval(round(($count / $threat_todo[$runtimename]) + 0.5,0) );
            $realtimests[] = [
                'status' => "$runtimename; worker task needed = ($count/".$threat_todo[$runtimename]." + 0.5) ",
                'count' => $taskneeded,
                'icon' => '',
            ];

            $actuelnumber = scartUsers::getGeneralOption('scartcheckonline_realtime_'.$runtimename.'_tasklastmax',0);
            $realtimests[] = [
                'status' => "$runtimename; actual (runtime) number workers ",
                'count' => $actuelnumber,
                'icon' => ($actuelnumber != $taskneeded) ? 'warning' : 'success',
            ];

            $unlocked = 'count'.$runtimename;
            $realtimests[] = [
                'status' => "$runtimename; checkonline records (locked/unlocked)",
                'count' => $locked[$runtimename].'/'.$$unlocked,
                'icon' => '',
            ];

        }

        $realtimests[] = [
            'status' => 'total records todo (locked/unlocked)',
            'count' => $countlocks.'/'.$countfrn,
            'icon' => '',
        ];

        $names = [];
        foreach ($createruntimes AS $runtimename => $runtimeneeded) {
            $actuelnumber = scartUsers::getGeneralOption('scartcheckonline_realtime_'.$runtimename.'_tasklastmax',0);
            for ($i=1;$i<=$actuelnumber;$i++) {
                $names[] = "$runtimename-$i";
            }
        }

        $totwork = 0;
        foreach ($names AS $name) {
            $cntwork = scartRealtimeCheckonline::getNamedCount($name);
            $len = strpos($name,'-');
            $runtimename = substr($name,0, $len);
            $realtimests[] = [
                'status' => "$name; messages for worker",
                'count' => $cntwork,
                'icon' => (($cntwork > $threat_todo[$runtimename]) ? 'warning' : ''),
            ];
            $totwork += $cntwork;
        }

        $realtimests[] = [
            'status' => 'difference between locked and work count',
            'count' => ($countlocks - $totwork),
            'icon' => (($countlocks - $totwork) != 0) ? 'warning' : 'success',
        ];

        return $realtimests;
    }



}
