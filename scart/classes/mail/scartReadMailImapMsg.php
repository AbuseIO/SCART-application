<?php namespace abuseio\scart\classes\mail;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;

class scartReadMailImapMsg {

    private $_msg = null;
    private $_body = null;

    public function __construct($msg) {
        $this->_msg = $msg;
    }

    public function getId() {
        return (isset($this->_msg->uid)) ? $this->_msg->uid : '??';
    }

    public function getSubject() {
        return (isset($this->_msg->subject)) ? $this->_msg->subject : '??';
    }

    public function getFrom() {
        return (isset($this->_msg->from)) ? $this->_msg->from : '??';
    }
    public function getDate() {
        return date('Y-m-d H:i:s',$this->_msg->udate);
    }

    function checkConvertIMAPbody($body) {

        // try to convert it into printable chars

        try {
            //scartLog::logDump("D-checkConvertIMAPbody; input body:\n",$body);
            $quoted =  Systemconfig::get('abuseio.scart::scheduler.import.readmailbox.imap.quoted',true);
            if ($quoted) {
                $body = quoted_printable_decode($body);
                //scartLog::logDump("D-checkConvertIMAPbody; quoted_printable_decode body:\n",$body);
            } else {
                scartLog::logLine("D-checkConvertIMAPbody; disabled convert quoted_printable body");
            }
            // if not TEXT ASCII format
            if (strpos($body,'=0D=0A')!==false) {
                scartLog::logLine("D-checkConvertIMAPbody; type=0D0A");
                // first combi \n\r
                $body = str_replace(["=\n", "=\r", "=\n\r"], '', $body);
                // then only cr or lf
                $body = str_replace(["\r", "\n"], '', $body);
                // then split
                $bodylines = explode("=0D=0A", $body);
            } else {
                scartLog::logLine("D-checkConvertIMAPbody; type=plain text/html");
                $body = str_replace(["\r\n", "\r"], "\n", $body);
                // split on crlf
                $bodylines = explode("\n", $body);
            }
        } catch (\Exception $err) {
            scartLog::logLine("W-checkConvertBody; error quoted_printable_decode: ".$err->getMessage());
        }
        return $bodylines;
    }

    public function getBody() {
        if ($this->_body == null) $this->_body = scartReadMailImap::imapGetMessageBody($this->_msg->msgno);
        return $this->_body;
    }


    public function getAttachments() {

        $attachments = scartReadMailImap::imapGetAttachments($this->_msg->msgno);

        // @to-do; make uniform attachment objects

        return $attachments;
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
