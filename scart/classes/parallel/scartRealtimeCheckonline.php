<?php
namespace abuseio\scart\classes\parallel;

use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\online\scartCheckOnline;
use abuseio\scart\classes\parallel\scartRuntime;
use abuseio\scart\classes\scheduler\scartScheduler;
use abuseio\scart\models\Systemconfig;
use parallel\{Future, Runtime, Channel, Error};
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\scheduler\scartSchedulerCheckOnline;

class scartRealtimeCheckonline {

    static private $logname='';
    static private $test_mode;                 // if testmode then up/down spinning of tasks to test performance/memory/scaling of server environment
    static private $start_normal_tasks;        // start number of normal task
    static private $inputs_max;                // max number of scart input records in each run (each minute)
    static private $check_online_every;        // min time in minutes after which scart input record will be checkonline again
    static private $every_secs;                // time between next scheduling tasks (workers)
    static private $min_diff_spindown;         // min time in minute before task is spin down
    static private $spin_up_sec;               // time for tasks to spin up
    static private $look_again;                // max minutes for looking again

    public static function initName() {

        self::$logname = 'schedulerRealtimeCheckonline';
        return self::$logname;
    }

    public static function init($settings=[]) {

        // ENV of SYSTEMCONFIG

        $defaults = [
            'test_mode' => false,
            'start_normal_tasks' => 1,
            'inputs_max' => Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_inputs_max',10),
            'check_online_every' => Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',15),
            'every_secs' => 60,
            'min_diff_spindown' => Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_min_diff_spindown',15),
            'spin_up_sec' => 25,
            'look_again' => Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120),
            // with 120 mins (2 hours) look each record (url) again
        ];
        $vars = array_merge($defaults,$settings);
        foreach ($vars AS $var => $value) {
            SELF::$$var = $value;
        }
        scartLog::logLine("D-schedulerRealtimeCheckonline; settings=" . print_r($vars,true));
        $memory_limit = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_memory_limit','1G');
        $memory_limit = scartScheduler::setMinMemory($memory_limit);
        scartLog::logLine("D-schedulerRealtimeCheckonline; memory_limit set on $memory_limit");
    }

    public static function run($settings=[]) {

        try {

            $logname = self::initName();

            register_shutdown_function(function () {
                echo "D-schedulerRealtimeCheckonline; shutdown, last error: " . print_r(error_get_last(),true);
            });

            set_exception_handler(function($errno, $errstr, $errfile, $errline ){
                throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            });

            $mode =  Systemconfig::get('abuseio.scart::scheduler.checkntd.mode',SCART_CHECKNTD_MODE_CRON);
            if ($mode != SCART_CHECKNTD_MODE_REALTIME) {
                scartLog::logLine("W-$logname; checkntd(online) mode is NOT realtime - STOP processing");
                exit();
            }

            self::init($settings);

            // monitor task
            $monitortask = plugins_path().'/abuseio/scart/classes/parallel/scartRealtimeMonitorTask.php';
            $monitorruntime = new scartRuntime('Monitor');
            $monitorfuture = $monitorruntime->initTask($monitortask);

            // runtime tasks
            $createruntimes = [
                'FirstTime' => 1,
                'Retry' => 1,
                'Normal' => self::$start_normal_tasks,
            ];
            $checkonlinetask = plugins_path().'/abuseio/scart/classes/parallel/scartRealtimeCheckonlineTask.php';
            $runtimeList = $runtimecontext = $futureList = $futureListTrash = [];
            foreach ($createruntimes AS $runtimename => $runtimeneeded) {
                for ($i=0;$i<$runtimeneeded;$i++) {
                    $taskname = $runtimename.'-'.($i + 1);
                    $runtimeList[$taskname] = new scartRuntime($taskname);
                    $futureList[$taskname] = $runtimeList[$taskname]->initTask($checkonlinetask);
                }
                $runtimecontext[$runtimename] = [
                    'taskneeded' => $runtimeneeded,
                    'taskcurrent' => 1,
                    'tasklastmax' => 1,
                    'tasklastset' => time(),
                ];
            }
            sleep(1);

            // go looping with dispatching checkonline jobs
            //$taskcurrent = 1;
            //$taskneeded = self::$start_normal_tasks;
            //$tasklastmax = self::$start_normal_tasks;
            //$tasklastset = time();
            $taskupdated = true;

            // at start reset all REALTIME named CHECKONLINE locks
            self::resetAllNamedLocks();

            scartLog::logLine("D-{$logname} go into while(true)...");
            while (true) {

                // first check if active and not in maintenance mode

                Systemconfig::readDatabase();  // always fresh data from config in database (eg maintenance mode)
                $active =  Systemconfig::get('abuseio.scart::scheduler.checkntd.active',true);
                if ($active) {
                    $maintenance = Systemconfig::get('abuseio.scart::maintenance.mode',false);
                    $active = !$maintenance;
                } else {
                    $maintenance = false;
                }
                scartUsers::setGeneralOption($logname,($active)?1:0);

                if ($active) {

                    /**
                     * (note: in text below we use the default values of the (timing) settings)
                     *
                     * Each 60 sec we check if there are checkonline inputs
                     * We push records to the tasks based on the standard deviation for each checkonline
                     * we push to a channel, so jobs are scheduled for threat
                     *
                     * There is a FirstTime, Retry (browser/whois errors) and Normal (rest) phase
                     *
                     * Check number of workers
                     * - Calculate number of task-workers needed
                     * - if more, then spin up
                     * - if less, then spin down
                     * - Spin more quicker up, then down
                     *
                     * Input
                     * - check_online_every; minimum time in minutes between checkking
                     * - realtime_look_again; minimum time in minutes in which a record must be checked again
                     * - realtime_inputs_max; maximum records within one minute for a worker
                     *
                     * Calculation
                     * - (realtime_look_again - check_online_every) = working time in minutes for one worker
                     * - records_in_one_minute; number of records a working can do (based on (dynamic) average standard deviation)
                     * - so one worker can do within the working time; (realtime_look_again - check_online_every) x records_in_one_minute
                     *
                     * Note: scartSchedulerSendAlerts is monitoring checkonline_lock/lastseen to check if workers are crashed or load is to heavy
                     *
                     */

                    // dynamic depending on local server (mem/cpu) capacity
                    $maxdatadragon = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_max_wrokers','8');
                    scartLog::logLine("D-{$logname}; got realtime_max_wrokers=$maxdatadragon ");

                    foreach ($createruntimes AS $runtimename => $runtimeneeded) {

                        // get record workload for each threat (Worker)
                        $threat_todo = self::calculateThreatTodo($runtimename);

                        // check needed running tasks
                        $count = scartSchedulerCheckOnline::countWork($runtimename);

                        // load from context
                        $taskcurrent = $runtimecontext[$runtimename]['taskcurrent'];
                        $tasklastmax = $runtimecontext[$runtimename]['tasklastmax'];
                        $tasklastset = $runtimecontext[$runtimename]['tasklastset'];

                        // calculate
                        $taskneeded = intval(round(($count / $threat_todo) + 0.5,0) );
                        scartLog::logLine("D-{$logname}; runtimename=$runtimename; taskneeded = $taskneeded = round(($count / $threat_todo) + 0.5) ");

                        if ($taskneeded > $maxdatadragon) {
                            scartLog::logLine("D-{$logname}; runtimename=$runtimename; taskneeded is more then max datadragon; limit on $maxdatadragon ");
                            $taskneeded = $maxdatadragon;
                        }

                        if ($taskneeded > $tasklastmax) {

                            // SPIN workers up

                            scartLog::logLine("D-{$logname}; need more workers for $runtimename;; tasks was=$tasklastmax, needed=$taskneeded; lastset=".date('Y-m-d H:i:s',$tasklastset));
                            for ($i=$tasklastmax;$i<$taskneeded;$i++) {
                                $taskname = $runtimename.'-'.($i + 1);
                                $runtimeList[$taskname] = new scartRuntime($taskname);
                                $futureList[$taskname] = $runtimeList[$taskname]->initTask($checkonlinetask);
                            }

                            $stdavg = scartSchedulerCheckOnline::checkStddevTime($runtimename);
                            $params = [
                                'reportname' => "RealtimeController report ",
                                'report_lines' => [
                                    'report time: ' . date('Y-m-d H:i:s'),
                                    "runtime: $runtimename",
                                    "number of records: " . $count,
                                    'stddev time (WhoIs & browser): '.$stdavg,
                                    "worker capacity within look again time : " . $threat_todo,
                                    "task calculation: $taskneeded = round(($count / $threat_todo) + 0.5) ",
                                    "spin workers UP; tasks was=$tasklastmax, needed=$taskneeded",
                                ]
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                            $tasklastmax = $taskneeded;
                            $tasklastset = time();
                            $createruntimes['Normal'] = $taskneeded;
                            $taskupdated = true;

                        } elseif ($taskneeded < $tasklastmax) {

                            // SPIN workers down (after min_diff_spindown minutes to avoid up-down-up-down-up...)

                            // take time to spin down - check if diffspindown minutes gone
                            $diffmin = intval((time() - $tasklastset)/60);
                            if ($diffmin >= self::$min_diff_spindown) {

                                scartLog::logLine("D-{$logname}; runtimename=$runtimename; spin workers down; mindiffspindwn=".self::$min_diff_spindown."; tasks was=$tasklastmax, needed=$taskneeded; lastset=".date('Y-m-d H:i:s',$tasklastset));

                                $stoperror = '';
                                $newtasklastmax = $tasklastmax;
                                for ($i=$tasklastmax - 1;$i>=$taskneeded;$i--) {
                                    $taskname = $runtimename.'-'.($i + 1);
                                    scartLog::logLine("D-{$logname}; cleanup (unset) worker '$taskname'");
                                    $cnt = self::getNamedCount($taskname);
                                    if (!$runtimeList[$taskname]->done($futureList[$taskname]) && $cnt < $runtimeList[$taskname]->maxChannel) {
                                        // push stop signal
                                        $runtimeList[$taskname]->sendChannel('stop');
                                    }
                                    if ($cnt > 0) {
                                        scartLog::logLine("D-{$logname}; task stil busy with messages (cnt=$cnt); cannot stop '$taskname' - wait until finished");
                                        $stoperror .= ($stoperror?', ':'').$taskname." (#messages=$cnt)";
                                    } elseif ($stoperror == '') {
                                        // only when $stoperror='' -> because taskname is numbering from high till low -> next time we cleanup

                                        // always check if really stopped
                                        $maxi = 3;
                                        while (!$runtimeList[$taskname]->done($futureList[$taskname]) && $maxi > 0) {
                                            sleep(3);
                                            scartLog::logLine("D-{$logname}; wait for stopping worker '$taskname' (maxi=$maxi) ");
                                            $maxi--;
                                        }
                                        if ($maxi > 0) {
                                            // unset runtime task
                                            $runtimeList[$taskname]->unset();
                                            // unset (move object to dump) future
                                            scartLog::logLine("D-{$logname}; unset future of worker '$taskname'");
                                            // note: when we nullify $futureList[$taskname] without saving pointer, we get a segment failed
                                            $futureListTrash[] = $futureList[$taskname];
                                            $futureList[$taskname] = null;
                                            scartLog::logLine("D-{$logname}; done cleanup of '$taskname'");
                                            $newtasklastmax--;
                                        } else {
                                            scartLog::logLine("W-{$logname}; worker CANNOT be stopped!?");
                                        }
                                    }
                                }


                                $stdavg = scartSchedulerCheckOnline::checkStddevTime($runtimename);
                                $params = [
                                    'reportname' => "RealtimeController report ",
                                    'report_lines' => [
                                        'report time: ' . date('Y-m-d H:i:s'),
                                        "runtime: $runtimename",
                                        "number of records: " . $count,
                                        'stddev time (WhoIs & browser): '.$stdavg,
                                        'worker capacity within look again time: '.$threat_todo,
                                        "task calculation: $taskneeded = round(($count / $threat_todo) + 0.5) ",
                                        "spin workers DOWN; tasks was=$tasklastmax, needed=$taskneeded",
                                        (($stoperror)? "cannot stop task(s); $stoperror " : 'task(s) stopped'),
                                    ]
                                ];
                                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                                $tasklastmax = $newtasklastmax;
                                $tasklastset = time();
                                if ($taskcurrent > $taskneeded) $taskcurrent = 1;

                            } else {
                                scartLog::logLine("D-{$logname}; runtimename=$runtimename; spin workers not yet down ($diffmin < ".self::$min_diff_spindown.") ");
                            }
                        } else {
                            // set last time task check when number of task is okay
                            $tasklastset = time();
                        }

                        // save $tasklastmax for stats
                        scartUsers::setGeneralOption('scartcheckonline_realtime_'.$runtimename.'_tasklastmax',$tasklastmax);

                        // save in context
                        $runtimecontext[$runtimename]['taskcurrent'] = $taskcurrent;
                        $runtimecontext[$runtimename]['tasklastmax'] = $tasklastmax;
                        $runtimecontext[$runtimename]['tasklastset'] = $tasklastset;
                        $runtimecontext[$runtimename]['taskneeded'] = $taskneeded;

                    }

                    if ($taskupdated) {
                        // wait some time to let workers start
                        scartLog::logLine("D-{$logname}; give worker(s) time (".self::$spin_up_sec." secs) to spin up");
                        sleep(self::$spin_up_sec);
                        $taskupdated = false;
                    }

                    // check the reports and if found then push to workers

                    $alreadydone = [];
                    foreach ($createruntimes AS $runtimename => $runtimeneeded) {

                        // load context
                        $taskcurrent = $runtimecontext[$runtimename]['taskcurrent'];
                        $taskneeded = $runtimecontext[$runtimename]['taskneeded'];

                        // (real) checkonline inputs
                        if ($runtimename == 'Normal') {
                            $inputs = scartSchedulerCheckOnline::Normal(self::$check_online_every);
                        } else {
                            $inputs = scartSchedulerCheckOnline::$runtimename();
                        }

                        // check always if not already done
                        $inputs = $inputs->select('id','filenumber')
                            ->whereNotIn('filenumber',$alreadydone)
                            ->orderBy('lastseen_at','ASC')
                            ->take(self::$inputs_max * $taskneeded)
                            ->get();

                        if ($inputs->count() > 0) {

                            scartLog::logLine("D-{$logname}; have $runtimename job(s); count inputs=".$inputs->count());

                            /**
                             * Fill task workers - based on working count so workers get equal load
                             * 1: get counts of workers
                             * 2: get max count
                             * 3: sort from low to high
                             * 4: loop
                             *      fill till max count
                             *      next worker
                             *
                             */

                            $taskcounters = [];
                            $maxcount = 0;
                            for ($nr=1;$nr<=$taskneeded;$nr++) {
                                $taskname = $runtimename.'-'.$nr;
                                $taskcounters[$taskname] = intval(self::getNamedCount($taskname));
                                if ($taskcounters[$taskname] > $maxcount) $maxcount = $taskcounters[$taskname];
                            }

                            asort($taskcounters);
                            //scartLog::logLine("D-{$logname}; taskcounters=".print_r($taskcounters,true));

                            foreach ($inputs as $input) {
                                $taskname = key($taskcounters);
                                //$cnt = self::getNamedCount($taskname);
                                $cnt = current($taskcounters);
                                if ($cnt < ($runtimeList[$taskname]->maxChannel - 1)) {
                                    $monitorruntime->sendChannel([
                                        'sender' => 'scartRealtimeCheckonline',
                                        'record_id' => $input->id,
                                        'filenumber' => $input->filenumber,
                                        'set' => true,
                                        'taskname' => $taskname,
                                    ]);
                                    scartLog::logLine("D-{$logname}; push job '$input->filenumber' to task '$taskname'; cnt=$cnt  ");
                                    $runtimeList[$taskname]->sendChannel($input->filenumber);
                                    $alreadydone[] = $input->filenumber;
                                    scartAlerts::alertAdminStatus('REALTIME_TASK_PUSH',$logname, false);
                                } else {
                                    $warning = "channel from task '$taskname' is full, count=$cnt - skip push";
                                    scartLog::logLine("W-{$logname}; $warning");
                                    scartAlerts::alertAdminStatus('REALTIME_TASK_PUSH',$logname, true, $warning, 3, 100 );
                                }
                                $taskcounters[$taskname] += 1;
                                if ($taskcounters[$taskname] > $maxcount) {
                                    if (next($taskcounters) === false) {
                                        reset($taskcounters);
                                    }
                                }
                            }

                        }

                        $runtimecontext[$runtimename]['taskcurrent'] = $taskcurrent;

                    }

                    scartLog::logMemory($logname);

                } else {
                    scartLog::logLine('D-'.$logname.'; NOT active ' . (($maintenance) ? '(MAINTENANCE MODE)' : '')  );
                }

                // mark end of running
                scartUsers::setGeneralOption($logname,0);

                // give room for workers (threats) do work
                scartLog::logLine("D-{$logname}; wait ".self::$every_secs." seconds ");
                sleep(self::$every_secs);

                // no need to cache - keep memory clean
                scartLog::resetLog();

            }

        } catch (\Exception $err) {

            scartLog::logLine("E-{$logname}; exception '" . $err->getMessage() . "', at line " . $err->getLine());

        }

    }

    /**
     * TESTMODE (UNITTEST)
     *
     * Generate up and down of task to test scaling and memory use
     *
     */

    static private $updownarr = [2,3,4,5,5,8,9,8,6,5,5,5,4,3,1];
    static private $updownind = 0;

    static function generateTask($tasks) {
        $min = intval(date('i'));
        if ($min % (self::$min_diff_spindown * 2) == 0) {
            $tasks = self::$updownarr[self::$updownind];
            scartLog::logLine("D-generateTask; get new task number (ind=".self::$updownind."); tasks=$tasks");
            self::$updownind++;
            if (self::$updownind >= count(self::$updownarr)) {
                self::$updownind = 0;
            }
        }
        return $tasks;
    }

    /**
     * The calculation of the number or records each threat can process within the given time
     *
     */

    public static function calculateRecordsEachMinute($runtimename='Normal') {

        self::$inputs_max = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_inputs_max',10);
        $stddev = scartSchedulerCheckOnline::checkStddevTime($runtimename);
        if ($runtimename != 'Normal' && empty($stddev)) {
            // when empty, then fallback to Normal
            $stddev = scartSchedulerCheckOnline::checkStddevTime('Normal');
        }
        // if stil empty fall back on 1 sec
        if (empty($stddev)) $stddev = 1;
        // max records each minute, rounded
        $records_in_one_minute = round(((60 / $stddev) - 0.5), 0);
        $records_in_one_minute = ($records_in_one_minute < 1) ? 1 : $records_in_one_minute;
        $records_in_one_minute = ($records_in_one_minute > self::$inputs_max) ? self::$inputs_max : $records_in_one_minute;
        scartLog::logLine("D-".self::$logname."; $runtimename worker max records within one minute: $records_in_one_minute");
        return $records_in_one_minute;
    }

    public static function calculateThreatTodo($runtimename='Normal') {

        // calculate number of records within one minute
        $records_in_one_minute = self::calculateRecordsEachMinute($runtimename);
        // dynamic runtime values
        self::$check_online_every = Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',15);
        self::$look_again = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120);
        // calculate max processing time in minutes
        $time_to_work = self::$look_again - self::$check_online_every;
        // calculate max records each worker
        $threat_todo = $time_to_work * $records_in_one_minute;
        scartLog::logLine("D-".self::$logname."; $runtimename threat work capacity = ((".self::$look_again."-".self::$check_online_every."=$time_to_work) x $records_in_one_minute) = $threat_todo ");
        return $threat_todo;
    }


    /**
     * REALTIME named counters for number of current locks (processing records)
     *
     */

    private static $namedprefix = 'scartRealtime_';

    public static function setNamedLock($id,$set,$name) {

        // disabled own set lock of record -> job of checkonline, not us
        //scartCheckOnline::setLock($id,$set);

        $optionname = self::$namedprefix.$name;
        $cnt = scartUsers::getGeneralOption($optionname);
        if (empty($cnt)) $cnt = 0;
        $cnt += ($set)?1:-1;
        scartLog::logLine("D-scartRealtimeCheckonline; ".(($set)?'SET':'RESET')." named lock; id=$id, set=$set, cnt=$cnt, name=$name");
        scartUsers::setGeneralOption($optionname,$cnt);
    }

    public static function getNamedCount($name) {

        $optionname = self::$namedprefix.$name;
        $cnt = scartUsers::getGeneralOption($optionname);
        if (empty($cnt)) $cnt = 0;
        return $cnt;
    }

    public static function resetNamedLock($name) {

        $optionname = self::$namedprefix.$name;
        scartLog::logLine("D-scartRealtimeCheckonline; reset named lock '$name'");
        scartUsers::setGeneralOption($optionname,0);
    }

    public static function resetAllNamedLocks() {

        scartCheckOnline::resetAllLocks();
        scartUsers::resetGeneralOption(self::$namedprefix);
    }

}
