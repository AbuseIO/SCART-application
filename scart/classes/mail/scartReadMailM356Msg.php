<?php namespace abuseio\scart\classes\mail;

use abuseio\scart\classes\helpers\scartLog;
use DateTimeInterface;

class scartReadMailM356Msg {


    private $_msg = null;
    private $_body = null;

    public function __construct($msg) {
        $this->_msg = $msg;
    }

    public function getId() {
        return $this->_msg->getId();
    }

    public function getSUbject() {
        return $this->_msg->getSubject();
    }

    public function getFrom() {
        return $this->_msg->getFrom()->getEmailAddress()->getAddress();
    }
    public function getDate() {
        return date('Y-m-d H:i:s',strtotime($this->_msg->getReceivedDateTime()->format(DateTimeInterface::RFC2822)));
    }


    public function getBody() {
        return $this->_msg->getBody()->getContent();
    }

    function checkConvertM356body($body) {

        // try to convert it into printable chars
        try {
            $body = quoted_printable_decode($body);
        } catch (\Exception $err) {
            scartLog::logLine("W-checkConvertBody; error quoted_printable_decode: ".$err->getMessage());
        }


        // first convert to ln
        $body = str_replace(['\n', '\n\r'], "\n", $body);
        // then remove
        $body = str_replace(['\n', '\r'], "", $body);
        // then split
        $bodylines = explode("\n", $body);
        return $bodylines;
    }

    public function getBodyLines() {

        $body = $this->getBody();
        return $this->checkConvertM356body($body);
    }

    public function getBodyLinesCount() {
        return count($this->getBodyLines());
    }

    public function delete() {

        scartReadMailM356::deleteMessage($this->_msg);
    }


}
