<?php  namespace abuseio\scart\classes\mail;

/**
 * Test script for reading import mailbox based on settings
 *
 * IMAP and Microsoft 365
 *
 * Note: SCHEDULER_READIMPORT_ACTIVE can be set on false
 *
 */

use Db;
use Config;
use Mail;
use Log;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\mail\scartReadMailM356;
use abuseio\scart\classes\mail\scartReadMailImap;

class scartReadMail {

    static private $_mode = 'imap';

    public static function parseRfc822($string) {
        $address = '';
        try {
            $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
            preg_match_all($pattern, $string, $matches, PREG_PATTERN_ORDER);
            //scartLog::logLine("I-scartIMAPmail: matches=".print_r($matches,true));
            if (count($matches[0]) > 0) {
                // pick only first element; when format is "email" <email> we got 2x address
                $address = (isset($matches[0][0])?$matches[0][0]:'');
            }
        } catch (\Exception $err) {
            scartLog::logLine("E-scartReadMail: error parseRfc822 on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
        }
        return $address;
    }

    public static function getMode() {

        return Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.mode','imap');
    }

    public static function init() {

        self::$_mode = self::getMode();
        return (self::$_mode=='imap') ? scartReadMailImap::init() : scartReadMailM356::init();
    }

    public static function getInboxMessages($maxmsgs=10) {

        return (self::$_mode=='imap') ? scartReadMailImap::imapGetInboxMessages($maxmsgs) : scartReadMailM356::getInboxMessages($maxmsgs);
    }

    public static function close() {

        return (self::$_mode=='imap') ? scartReadMailImap::closeExpunge() : scartReadMailM356::close();
    }

}
