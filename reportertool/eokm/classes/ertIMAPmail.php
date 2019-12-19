<?php  namespace reportertool\eokm\classes;

use Db;
use Config;
use Mail;
use Log;

class ertIMAPmail {

    static private $_host = null;
    static private $_port = null;
    static private $_sslflag = null;
    static private $_username = null;
    static private $_password = null;
    static private $_client = null;

    public static function parseRfc822($string) {
        $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
        preg_match_all($pattern, $string, $matches);
        $address = (count($matches) > 0) ? implode('',$matches[0]) : '';
        return $address;
    }

    public static function setConfig($config) {
        self::$_host = (isset($config['host'])) ? $config['host'] : '';
        self::$_port = (isset($config['port'])) ? $config['port'] : '143';
        self::$_sslflag = (isset($config['sslflag'])) ? $config['sslflag'] : '/novalidate-cert';
        self::$_username = (isset($config['username'])) ? $config['username'] : '';
        self::$_password = (isset($config['password'])) ? $config['password'] : '';
    }

    /** IMAP CLIENT **/

    public static function imapInit() {

        // Connect to mailbox

        if (self::$_host) {

            /**
             * 2019/8/22;
             * - plesk bioffice01.nl -> port=993, sslflag=/ssl/novalidate-cert
             * - abusereportertool.com -> port=143, sslflag=/novalidate-cert
             *
             */

            $mailbox = '{'.self::$_host.':'.self::$_port.self::$_sslflag.'}INBOX';

            try {

                //ertLog::logLine("D-ertIMAPmail: imap_open($mailbox, $username)");
                SELF::$_client = imap_open($mailbox, self::$_username, self::$_password);

            } catch (\Exception $err) {
                ertLog::logLine("E-ertIMAPmail: exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            }


        } else {
            ertLog::logLine("E-ertIMAPmail: importExport config NOT set!?");
        }

        return (SELF::$_client != null);
    }

    public static function imapGetInboxMessages() {

        $headers = '';

        if (SELF::$_client==null) SELF::imapInit();

        if (SELF::$_client!=null) {
            $imap = imap_check(SELF::$_client);
            if ($imap->Nmsgs >= 1) {
                $headers = imap_fetch_overview(SELF::$_client, "1:$imap->Nmsgs", 0);
            }
        } else {
            ertLog::logLine("E-ertIMAPmail: No IMAP clientcontext ");
        }

        return $headers;
    }

    public static function imapGetMessageBody($msg_numer) {

        if (SELF::$_client==null) SELF::imapInit();
        if (SELF::$_client!=null) {
            // body text part
            $body = imap_fetchbody (SELF::$_client, $msg_numer, 1);
        } else {
            $body = '';
        }
        return $body;
    }

    public static function imapDeleteMessage($msg_numer) {
        if (SELF::$_client==null) SELF::imapInit();
        if (SELF::$_client!=null) {
            imap_delete(SELF::$_client,$msg_numer);
        }
    }

    public static function closeExpunge() {
        if (SELF::$_client==null) SELF::imapInit();
        if (SELF::$_client!=null) {
            imap_expunge(SELF::$_client);
            imap_close(SELF::$_client);
            SELF::$_client = null;
        }
    }



}
