<?php
namespace abuseio\scart\classes\parallel;

use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\online\scartCheckOnline;
use abuseio\scart\classes\scheduler\scartScheduler;
use abuseio\scart\models\Systemconfig;
use parallel\{Future, Runtime, Channel, Error};
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\parallel\scartRuntime;
use abuseio\scart\classes\scheduler\scartSchedulerCheckOnline;

class scartRealtimeCheckonline {

    static private $test_mode;                 // if testmode then up/down spinning of tasks to test performance/memory/scaling of server environment
    static private $start_normal_tasks;        // start number of normal task
    static private $inputs_max;                // max number of scart input records in each run (each minute)
    static private $check_online_every;        // min time in minutes after which scart input record will be checkonline again
    static private $every_secs;                // time between next scheduling tasks (workers)
    static private $min_diff_spindown;         // min time in minute before task is spin down
    static private $admin_report_min;          // every min admin report
    static private $spin_up_sec;               // time for tasks to spin up
    static private $look_again;                // max minutes for looking again

    public static function init($settings=[]) {

        // ENV of SYSTEMCONFIG

        $defaults = [
            'test_mode' => false,
            'start_normal_tasks' => 1,
            'inputs_max' => Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_inputs_max',10),
            'check_online_every' => Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',15),
            'every_secs' => 60,
            'min_diff_spindown' => Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_min_diff_spindown',15),
            'admin_report_min' => 15,
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

            // Note: prefix is 'scheduler' to get detected for maintenance mode
            $logname = 'schedulerRealtimeCheckonline';

            register_shutdown_function(function () {
                echo "D-schedulerRealtimeCheckonline; shutdown, last error: " . print_r(error_get_last(),true);
            });

            set_exception_handler(function($errno, $errstr, $errfile, $errline ){
                throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            });

            $mode =  Systemconfig::get('abuseio.scart::scheduler.checkntd.mode',SCART_CHECKNTD_MODE_CRON);
            if ($mode != SCART_CHECKNTD_MODE_REALTIME) {
                scartLog::logLine("W-{$logname}; checkntd(online) mode is NOT realtime - STOP processing");
                exit();
            } else {
                scartLog::logLine("D-{$logname}; checkntd(online) mode is realtime - START processing");
            }

            self::init($settings);

            $createruntimes = [
                'FirstTime' => 1,
                'Retry' => 1,
                'Normal' => self::$start_normal_tasks,
            ];

            // checkonline function
            $checkonlinetask = plugins_path().'/abuseio/scart/classes/parallel/scartRealtimeCheckonlineTask.php';

            // create runtime objects
            $futureList = $futureListTrash = [];
            $runtimeList = [];
            foreach ($createruntimes AS $name => $number) {
                if (in_array($name,['FirstTime','Retry'])) {
                    $runtimeList[$name] = new scartRuntime($name);
                    $futureList[$name] = $runtimeList[$name]->initTask($checkonlinetask);
                } else {
                    for ($i=0;$i<$number;$i++) {
                        $taskname = $name.'-'.($i + 1);
                        $runtimeList[$taskname] = new scartRuntime($taskname);
                        $futureList[$taskname] = $runtimeList[$taskname]->initTask($checkonlinetask);
                    }
                }
            }

            // go looping with dispatching checkonline jobs
            $taskcurrent = 1;
            $taskneeded = self::$start_normal_tasks;
            $tasklastmax = self::$start_normal_tasks;
            $tasklastset = time();
            $taskupdated = true;

            // at start reset all CHECKONLINE locks
            scartCheckOnline::resetAllLocks();

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
                     * We push max 10 records to the tasks (avg 5 sec for each checkonline)
                     * We push buffered, so inputs are scheduled for threat
                     *
                     * Between checkonline from an input, there is always 15 minutes
                     *
                     * Check number of normal scart inputs
                     * - Calculate number of task-workers needed
                     * - if more, then spin up
                     * - if less, then spin down
                     * - Spin more quicker up, then down
                     *
                     * Note: scartSchedulerSendAlerts is monitoring lastseen to check if workers is crashed or load is to heavy
                     *
                     * Note: TESTMODE is included because of good performance/scaling/stability testing; threating can
                     *
                     */

                    // Re-init; can be dynamic be tuned
                    self::$check_online_every = Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',15);
                    self::$inputs_max = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_inputs_max',10);
                    self::$look_again = Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120);
                    $time_to_work = self::$look_again - self::$check_online_every;
                    $threat_todo = $time_to_work * self::$inputs_max;  // number of records of each threat if look within (look_again) mins again
                    scartLog::logLine("D-{$logname}; threat work capacity = ((".self::$look_again."-".self::$check_online_every."=$time_to_work) x ".self::$inputs_max.") = $threat_todo ");

                    // check needed running tasks
                    $count = scartSchedulerCheckOnline::Normal(0)->count();

                    if (self::$test_mode) {
                        // force task number up and down
                        $taskneeded = self::generateTask($taskneeded);
                        scartLog::logLine("D-{$logname}; TESTMODE; taskneeded = $taskneeded ");
                    } else {
                        // calculate + 1
                        $taskneeded = intval(round(($count / $threat_todo) + 0.5,0) );
                        scartLog::logLine("D-{$logname}; taskneeded = $taskneeded = round(($count / $threat_todo) + 0.5) ");
                        // check needed running task for current total load
                        $workloadcount = scartSchedulerCheckOnline::Normal(self::$look_again)->count();     // records with more then (look_again) minutes last check
                        // extra one if behind (> 10)
                        $taskloadneeded = ($workloadcount > 10) ? $taskneeded + 1 : $taskneeded;
                        scartLog::logLine("D-{$logname}; taskloadneeded = $taskloadneeded ((workload=$workloadcount) > 10)");
                        if ($taskloadneeded > $taskneeded) {
                            $taskneeded = $taskloadneeded;
                            scartLog::logLine("D-{$logname}; switch to higher taskloadneeded");
                        }
                    }

                    if ($taskneeded > $tasklastmax) {

                        // SPIN workers up

                        scartLog::logLine("D-{$logname}; need more workers; tasks was=$tasklastmax, needed=$taskneeded; lastset=".date('Y-m-d H:i:s',$tasklastset));
                        for ($i=$tasklastmax;$i<$taskneeded;$i++) {
                            $taskname = $name.'-'.($i + 1);
                            $runtimeList[$taskname] = new scartRuntime($taskname);
                            $futureList[$taskname] = $runtimeList[$taskname]->initTask($checkonlinetask);
                        }

                        $params = [
                            'reportname' => "RealtimeController report ",
                            'report_lines' => [
                                'report time: ' . date('Y-m-d H:i:s'),
                                "number of normal records: " . $count,
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

                            scartLog::logLine("D-{$logname}; spin workers down; mindiffspindwn=".self::$min_diff_spindown."; tasks was=$tasklastmax, needed=$taskneeded; lastset=".date('Y-m-d H:i:s',$tasklastset));

                            for ($i=$tasklastmax - 1;$i>=$taskneeded;$i--) {
                                $taskname = $name.'-'.($i + 1);
                                scartLog::logLine("D-{$logname}; cleanup (unset) worker '$taskname'");
                                // push stop signal
                                $runtimeList[$taskname]->sendChannel('stop');
                                while (!$runtimeList[$taskname]->done($futureList[$taskname])) {
                                    sleep(3);
                                    scartLog::logLine("D-{$logname}; wait for stopping worker '$taskname'");
                                }
                                // unset runtime task
                                $runtimeList[$taskname]->unset();
                                // unset (move object to dump) future
                                scartLog::logLine("D-{$logname}; unset future of worker '$taskname'");
                                // note: when we nullify $futureList[$taskname] without saving pointer, we get a segment failed
                                $futureListTrash[] = $futureList[$taskname];
                                $futureList[$taskname] = null;
                                scartLog::logLine("D-{$logname}; done cleanup of '$taskname'");
                            }

                            $params = [
                                'reportname' => "RealtimeController report ",
                                'report_lines' => [
                                    'report time: ' . date('Y-m-d H:i:s'),
                                    "number of normal records: " . $count,
                                    "spin workers DOWN; tasks was=$tasklastmax, needed=$taskneeded",
                                ]
                            ];
                            scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                            $tasklastmax = $taskneeded;
                            $tasklastset = time();
                            $createruntimes['Normal'] = $taskneeded;
                            if ($taskcurrent > $taskneeded) $taskcurrent = 1;

                        } else {
                            scartLog::logLine("D-{$logname}; spin workers not yet down ($diffmin < ".self::$min_diff_spindown.") ");
                        }
                    } else {
                        // set last time task check when number of task is okay
                        $tasklastset = time();
                    }

                    // save $tasklastmax for stats
                    scartUsers::setGeneralOption('scartcheckonline_realtime_tasklastmax',$tasklastmax);

                    if ($taskupdated) {
                        // wait some time to let workers start
                        scartLog::logLine("D-{$logname}; give worker(s) time (".self::$spin_up_sec." secs) to spin up");
                        sleep(self::$spin_up_sec);
                        $taskupdated = false;
                    }

                    // check for each type the reports and if found then push to workers

                    $firstretry = [];
                    foreach ($createruntimes AS $name => $number) {

                        // send filenumbers to tasks, if there are input to checkonline

                        if (in_array($name,['FirstTime','Retry'])) {

                            // FirstTime or Retry
                            $inputs = scartSchedulerCheckOnline::$name();

                            $inputs = $inputs->select('id','filenumber')
                                ->take(self::$inputs_max)
                                ->get();
                            if ($inputs->count() > 0) {
                                scartLog::logLine("D-{$logname}; have $name job(s); count inputs=".$inputs->count());
                                foreach ($inputs AS $input) {
                                    scartLog::logLine("D-{$logname}; push job '$input->filenumber' to task '$name'");
                                    $runtimeList[$name]->sendChannel($input->filenumber);
                                    // use checkonline lock to mark PUSH and skip in the scartSchedulerCheckOnline selects
                                    scartCheckOnline::setLock($input->id,true);
                                    $firstretry[] = $input->filenumber;
                                }
                            }

                        } else {

                            // Normal (real) checkonline inputs
                            $inputs = scartSchedulerCheckOnline::Normal(self::$check_online_every);

                            // check always if not already done above in Firsttime or Retry -> workers are quick
                            $inputs = $inputs->select('id','filenumber')
                                ->whereNotIn('filenumber',$firstretry)
                                ->orderBy('lastseen_at','ASC')
                                ->take(self::$inputs_max * $number)
                                ->get();

                            if ($inputs->count() > 0) {

                                scartLog::logLine("D-{$logname}; have normal job(s); count inputs=".$inputs->count());
                                foreach ($inputs as $input) {
                                    $taskname = $name.'-'.$taskcurrent;
                                    scartLog::logLine("D-{$logname}; push job '$input->filenumber' to task '$taskname'");
                                    $runtimeList[$taskname]->sendChannel($input->filenumber);
                                    // use checkonline lock to mark PUSH and skip in the scartSchedulerCheckOnline selects
                                    scartCheckOnline::setLock($input->id,true);
                                    $taskcurrent += 1;
                                    if ($taskcurrent > $number) $taskcurrent = 1;
                                }
                            }

                        }

                    }

                    scartLog::logMemory($logname);

                } else {
                    scartLog::logLine('D-'.$logname.'; NOT active ' . (($maintenance) ? '(MAINTENANCE MODE)' : '')  );
                }

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


}
