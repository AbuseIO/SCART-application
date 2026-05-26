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

    public function getSubject() {
        return $this->_msg->getSubject();
    }

    public function getFrom() {
        return ($this->_msg->getFrom()) ? $this->_msg->getFrom()->getEmailAddress()->getAddress() : null;
    }
    public function getDate() {
        return date('Y-m-d H:i:s',strtotime($this->_msg->getReceivedDateTime()->format(DateTimeInterface::RFC2822)));
    }


    public function getBody() {
        return ($this->_msg->getBody()) ? $this->_msg->getBody()->getContent() : '';
    }

    function checkConvertM356body($body) {

        // try to convert it into printable chars

        try {
//            scartLog::logDump("D-checkConvertM356body; input body:",$body);
            $body = quoted_printable_decode($body);
//            scartLog::logDump("D-checkConvertM356body; quoted_printable_decode body:",$body);
        } catch (\Exception $err) {
            scartLog::logLine("W-checkConvertM356body; error quoted_printable_decode: ".$err->getMessage());
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

    public function getAttachments() {

        $ms356attachments = scartReadMailM356::getAttachments($this->getId());
        //scartLog::logDump("D-MS356 getAttachments=",$attachments);

        $attachments = [];
        foreach ($ms356attachments as $ms356attachment) {
            $attachments[] = [
                'filename' => $ms356attachment['filename'],
                'contentType' => $ms356attachment['contentType'],
                'attachment' => $ms356attachment['content'],
            ];
        }
        return $attachments;
    }


    public function delete() {

        scartReadMailM356::deleteMessage($this->_msg);
    }


}
