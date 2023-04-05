<?php
namespace abuseio\scart\classes\iccam\api3\classes;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;

class ScartGenericICCAMV3 {

    protected $maxresults = 20;

    protected $_status = 0;
    protected $_status_text = '';
    protected $_postdata = '';

    protected $_status_timestamp = '';
    protected $_loglines = [];
    protected  $_posts = [];

    protected function resetLoglines() {
        $this->_loglines = [];
        $this->_status_timestamp = '[' . date('Y-m-d H:i:s') . '] ';
    }
    protected function addLogline($logline) {
        $this->_loglines[] = $this->_status_timestamp . $logline;
        scartLog::logLine("D-$logline");
    }
    protected function returnLoglines() {
        return $this->_loglines;
    }
    protected function bLoglines() {
        return (count($this->_loglines) > 0);
    }

    protected function resetPosts() {
        $this->_posts = [];
    }
    protected function addPosts($post) {
        $this->_posts[] = $post;
    }
    protected function bPosts() {
        return (count($this->_posts) > 0);
    }
    protected function getPosts() {
        return print_r($this->_posts,true);
    }


}
