<?php
namespace abuseio\scart\classes\parallel;

/**
 * scart Runtime class
 *
 * ...
 *
 *
 */

use abuseio\scart\classes\helpers\scartLog;
use Config;
use parallel\{Future, Runtime, Channel};

class scartRuntime {

    private $runtime = null;
    private $channel = null;
    private $task = null;
    private $name = '';
    private $running = 0;
    private $maxChannel = 1000;   // max number of buffered messages in channel

    public function __construct($name) {

        $this->name = $name;
    }

    public function initTask($taskfile) {

        $this->runtime = new Runtime();
        // Note: create not Channel::Infinite because then we can crash the whole server without of memory when a task crashes
        // scartSchedulerSendAlerts is monitoring realtime operation and alert operator if tasks are stuck
        $this->channel = Channel::make($this->name,$this->maxChannel);
        $basepath = base_path();
        $task = require $taskfile;
        $future = $this->runtime->run($task,[$this->name,$basepath]);
        scartLog::logLine("D-scartRuntime[$this->name]; init task (with channel) created");
        $this->running += 1;
        return $future;
    }

    public function initChannel() {

        $this->channel = Channel::open($this->name);
    }

    public function readChannel() {

        return $this->channel->recv();
    }

    public function sendChannel($data) {

        return $this->channel->send($data);
    }

    public function done($future) {

        $done = $future->done();
        if ($done) {
            // set done
            $this->running -= 1;
            scartLog::logLine("D-scartRuntime[$this->name]; task (running=$this->running) done");
        } else {
            scartLog::logLine("D-scartRuntime[$this->name]; task (running=$this->running) NOT done yet");
        }
        return $done;
    }

    public function unset() {

        // close -> wait for task scheduled to close
        scartLog::logLine("D-scartRuntime[$this->name]; close channel");
        $this->channel->close();
        $this->channel = null;
        scartLog::logLine("D-scartRuntime[$this->name]; kill runtime");
        //$this->runtime->kill();   // segment fail!?
        $this->runtime = null;
        // set closed
        scartLog::logLine("D-scartRuntime[$this->name]; unset");
    }

}
