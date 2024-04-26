<?php namespace abuseio\scart\classes\mail;

use abuseio\scart\classes\helpers\scartLog;

class scartReadMailImapMsg {

    private $_msg = null;
    private $_body = null;

    public function __construct($msg) {
        $this->_msg = $msg;
    }

    public function getId() {
        return $this->_msg->uid;
    }

    public function getSUbject() {
        return $this->_msg->subject;
    }

    public function getFrom() {
        return $this->_msg->from;
    }
    public function getDate() {
        return date('Y-m-d H:i:s',$this->_msg->udate);
    }

    function checkConvertIMAPbody($body) {

        // try to convert it into printable chars
        try {
            $body = quoted_printable_decode($body);
        } catch (\Exception $err) {
            scartLog::logLine("W-checkConvertBody; error quoted_printable_decode: ".$err->getMessage());
        }
        // if not TEXT ASCII format
        if (strpos($body,'=0D=0A')!==false) {
            scartLog::logLine("D-checkConvertBody; type=0D0A");
            // first combi \n\r
            $body = str_replace(["=\n", "=\r", "=\n\r"], '', $body);
            // then only cr or lf
            $body = str_replace(["\r", "\n"], '', $body);
            // then split
            $bodylines = explode("=0D=0A", $body);
        } else {
            scartLog::logLine("D-checkConvertBody; type=plain text/html");
            // split on crlf
            $bodylines = explode("\n", $body);
        }
        return $bodylines;
    }

    public function getBody() {
        if ($this->_body == null) $this->_body = scartReadMailImap::imapGetMessageBody($this->_msg->msgno);
        return $this->_body;
    }

    public function getBodyLines() {
        return $this->checkConvertIMAPbody($this->getBody());
    }

    public function getBodyLinesCount() {
        return count($this->getBodyLines());
    }

    public function delete() {
        scartReadMailImap::imapDeleteMessage($this->_msg->msgno);
    }



}
